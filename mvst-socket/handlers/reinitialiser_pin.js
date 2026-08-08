const admin = require('firebase-admin');
const express = require('express');
const router = express.Router();

// Initialiser Firebase Admin si pas encore fait
if (!admin.apps.length) {
  const serviceAccount = require('../serviceAccountKey.json');
  admin.initializeApp({
    credential: admin.credential.cert(serviceAccount),
  });
}

// POST /reinitialiser_pin
router.post('/', async (req, res) => {
  const { telephone, nouveauPin } = req.body;

  if (!telephone || !nouveauPin) {
    return res.json({ success: false, message: 'Paramètres manquants' });
  }

  try {
    // Trouver l'utilisateur par email (telephone@gmail.com)
    const email = `${telephone}@gmail.com`;
    const userRecord = await admin.auth().getUserByEmail(email);

    // Changer le mot de passe
    await admin.auth().updateUser(userRecord.uid, {
      password: nouveauPin + 'mv',
    });

    return res.json({ success: true });
  } catch (e) {
    return res.json({ success: false, message: e.message });
  }
});

module.exports = router;
