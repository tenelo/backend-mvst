const express = require('express');
const pool = require('../db');
const { envoyerNotification } = require('./envoi_notifications');

const router = express.Router();

// POST /alerte-affluence/notifier
// Body : { depart, destination, ligne, heure, date, type, numeroCar, vendus, seuil }
// Cible : admins superadmin (toutes gares) OU admins rattaches a la gare de depart.
// Best-effort : ne bloque jamais la vente en amont.
router.post('/notifier', async (req, res) => {
  const {
    depart,
    destination,
    ligne,
    heure,
    date,
    type,
    numeroCar,
    vendus,
    seuil,
  } = req.body || {};

  if (!depart || !heure || !type) {
    return res.status(200).json({ success: false, message: 'depart, heure et type requis' });
  }

  const client = await pool.connect();
  try {
    const adminsRes = await client.query(
      `SELECT "idUtilisateur" FROM "Admins"
       WHERE role = 'superadmin' OR gare = $1`,
      [depart]
    );
    const idsAdmins = adminsRes.rows.map((r) => r.idUtilisateur);

    if (idsAdmins.length === 0) {
      return res.status(200).json({ success: true, destinataires: 0, envoyes: 0, message: 'Aucun admin cible' });
    }

    const dest = await client.query(
      `SELECT token FROM "DeviceTokens"
       WHERE type_compte = 'admin' AND id_compte = ANY($1)`,
      [idsAdmins]
    );
    const tokens = dest.rows.map((r) => r.token);

    if (tokens.length === 0) {
      return res.status(200).json({ success: true, destinataires: idsAdmins.length, envoyes: 0, message: 'Aucun appareil admin joignable' });
    }

    const libelleLigne = (ligne && String(ligne).trim() !== '')
      ? ligne
      : `${depart} ${destination || ''}`.trim();
    const libelleCar = numeroCar ? `Car n°${numeroCar}` : 'Car';
    const titre = 'Affluence — pensez à positionner un car';
    const corps = `${libelleCar} ${String(type).toUpperCase()} ${libelleLigne} de ${heure}${date ? ' le ' + String(date).replace(/_/g, ' ') : ''} : ${vendus}/${seuil} places. Plus que 10 places libres avant le plein.`;

    const r = await envoyerNotification(tokens, titre, corps, {
      type: 'alerte_affluence',
      depart: depart,
      heure: heure,
      typeVoyage: type,
      numeroCar: numeroCar || 1,
      vendus: vendus,
      seuil: seuil,
    });

    return res.status(200).json({
      success: true,
      destinataires: idsAdmins.length,
      appareils: tokens.length,
      envoyes: r.succes,
      echecs: r.echecs,
      purges: r.invalidesPurges,
    });
  } catch (err) {
    console.error('Erreur alerte affluence:', err);
    return res.status(200).json({ success: false, message: 'Erreur serveur' });
  } finally {
    client.release();
  }
});

module.exports = router;
