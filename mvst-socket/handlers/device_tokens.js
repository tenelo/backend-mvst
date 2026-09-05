const express = require('express');
const pool = require('../db');

const router = express.Router();

// Enregistrement (upsert par token) d'un token FCM
router.post('/enregistrer', async (req, res) => {
  const { type_compte, id_compte, token, plateforme } = req.body || {};

  if (!type_compte || !id_compte || !token) {
    return res.status(200).json({ success: false, message: 'Parametres manquants' });
  }
  if (type_compte !== 'utilisateur' && type_compte !== 'admin') {
    return res.status(200).json({ success: false, message: 'type_compte invalide' });
  }

  const client = await pool.connect();
  try {
    await client.query('BEGIN');
    await client.query(
      `INSERT INTO "DeviceTokens" (type_compte, id_compte, token, plateforme, date_creation, date_derniere_utilisation)
       VALUES ($1, $2, $3, $4, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
       ON CONFLICT (token) DO UPDATE
       SET type_compte = EXCLUDED.type_compte,
           id_compte = EXCLUDED.id_compte,
           plateforme = EXCLUDED.plateforme,
           date_derniere_utilisation = CURRENT_TIMESTAMP`,
      [type_compte, String(id_compte), token, plateforme || null]
    );
    await client.query('COMMIT');
    return res.status(200).json({ success: true, message: 'Token enregistre' });
  } catch (err) {
    await client.query('ROLLBACK');
    console.error('Erreur enregistrement token FCM:', err);
    return res.status(200).json({ success: false, message: 'Erreur serveur' });
  } finally {
    client.release();
  }
});

// Suppression d'un token FCM (ex. logout / token invalide)
router.post('/supprimer', async (req, res) => {
  const { token } = req.body || {};

  if (!token) {
    return res.status(200).json({ success: false, message: 'Parametres manquants' });
  }

  const client = await pool.connect();
  try {
    await client.query('BEGIN');
    await client.query('DELETE FROM "DeviceTokens" WHERE token = $1', [token]);
    await client.query('COMMIT');
    return res.status(200).json({ success: true, message: 'Token supprime' });
  } catch (err) {
    await client.query('ROLLBACK');
    console.error('Erreur suppression token FCM:', err);
    return res.status(200).json({ success: false, message: 'Erreur serveur' });
  } finally {
    client.release();
  }
});

module.exports = router;
