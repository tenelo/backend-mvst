const express = require('express');
const pool = require('../db');
const { envoyerNotification } = require('./envoi_notifications');

const router = express.Router();

// POST /notif-suggestions/nouvelle  { message, categorie }
// Notifie superadmins + admins habilites (peutGererSuggestions=true).
router.post('/nouvelle', async (req, res) => {
  const { message, categorie } = req.body || {};

  const client = await pool.connect();
  try {
    // Destinataires : superadmins OU admins habilites, avec idUtilisateur non nul
    const dest = await client.query(
      `SELECT dt.token
       FROM "Admins" a
       JOIN "DeviceTokens" dt
         ON dt.id_compte = a."idUtilisateur" AND dt.type_compte = 'admin'
       WHERE a."idUtilisateur" IS NOT NULL
         AND (a.role = 'superadmin' OR a."peutGererSuggestions" = true)`
    );

    const tokens = dest.rows.map((r) => r.token);
    if (tokens.length === 0) {
      return res.status(200).json({ success: true, envoyes: 0, message: 'Aucun destinataire' });
    }

    const titre = 'Nouvelle suggestion';
    const corps = categorie ? `[${categorie}] ${message || ''}`.trim() : (message || 'Une nouvelle suggestion a ete envoyee');

    const r = await envoyerNotification(
      tokens,
      titre,
      corps.length > 120 ? corps.substring(0, 117) + '...' : corps,
      { type: 'nouvelle_suggestion' }
    );

    return res.status(200).json({ success: true, envoyes: r.succes, echecs: r.echecs, purges: r.invalidesPurges });
  } catch (err) {
    console.error('Erreur notif nouvelle suggestion:', err);
    return res.status(200).json({ success: false, message: 'Erreur serveur' });
  } finally {
    client.release();
  }
});

// POST /notif-suggestions/reponse  { idutilisateur, reponse }
// Notifie l'utilisateur auteur qu'une reponse a ete apportee a sa suggestion.
router.post('/reponse', async (req, res) => {
  const { idutilisateur, reponse } = req.body || {};

  if (!idutilisateur) {
    return res.status(200).json({ success: false, message: 'idutilisateur manquant' });
  }

  const client = await pool.connect();
  try {
    const dest = await client.query(
      `SELECT token FROM "DeviceTokens"
       WHERE type_compte = 'utilisateur' AND id_compte = $1`,
      [String(idutilisateur)]
    );

    const tokens = dest.rows.map((r) => r.token);
    if (tokens.length === 0) {
      return res.status(200).json({ success: true, envoyes: 0, message: 'Aucun destinataire' });
    }

    const corps = reponse && reponse.length > 0
      ? (reponse.length > 120 ? reponse.substring(0, 117) + '...' : reponse)
      : 'Votre suggestion a ete traitee';

    const r = await envoyerNotification(
      tokens,
      'Reponse a votre suggestion',
      corps,
      { type: 'reponse_suggestion' }
    );

    return res.status(200).json({ success: true, envoyes: r.succes, echecs: r.echecs, purges: r.invalidesPurges });
  } catch (err) {
    console.error('Erreur notif reponse suggestion:', err);
    return res.status(200).json({ success: false, message: 'Erreur serveur' });
  } finally {
    client.release();
  }
});

module.exports = router;
