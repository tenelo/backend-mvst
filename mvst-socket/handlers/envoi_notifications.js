const admin = require('firebase-admin');
const pool = require('../db');

if (!admin.apps.length) {
  const serviceAccount = require('../serviceAccountKey.json');
  admin.initializeApp({
    credential: admin.credential.cert(serviceAccount),
  });
}

async function envoyerNotification(tokens, titre, corps, data = {}) {
  if (!Array.isArray(tokens) || tokens.length === 0) {
    return { succes: 0, echecs: 0, invalidesPurges: 0 };
  }

  const dataStr = {};
  for (const [k, v] of Object.entries(data)) {
    dataStr[k] = String(v);
  }

  const message = {
    notification: { title: titre, body: corps },
    data: dataStr,
    tokens: tokens,
  };

  let reponse;
  try {
    reponse = await admin.messaging().sendEachForMulticast(message);
  } catch (err) {
    console.error('Erreur envoi FCM:', err);
    return { succes: 0, echecs: tokens.length, invalidesPurges: 0 };
  }

  const tokensInvalides = [];
  reponse.responses.forEach((r, i) => {
    if (!r.success) {
      const code = r.error && r.error.code;
      if (
        code === 'messaging/registration-token-not-registered' ||
        code === 'messaging/invalid-registration-token' ||
        code === 'messaging/invalid-argument'
      ) {
        tokensInvalides.push(tokens[i]);
      }
    }
  });

  let invalidesPurges = 0;
  if (tokensInvalides.length > 0) {
    const client = await pool.connect();
    try {
      await client.query('BEGIN');
      const res = await client.query(
        'DELETE FROM "DeviceTokens" WHERE token = ANY($1)',
        [tokensInvalides]
      );
      await client.query('COMMIT');
      invalidesPurges = res.rowCount;
    } catch (err) {
      await client.query('ROLLBACK');
      console.error('Erreur purge tokens invalides:', err);
    } finally {
      client.release();
    }
  }

  return {
    succes: reponse.successCount,
    echecs: reponse.failureCount,
    invalidesPurges,
  };
}

module.exports = { envoyerNotification };
