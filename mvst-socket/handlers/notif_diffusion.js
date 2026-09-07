const express = require('express');
const pool = require('../db');
const { envoyerNotification } = require('./envoi_notifications');

const router = express.Router();

// Lit une valeur numerique depuis Parametres (fallback si absente/illisible).
async function lireParametreNombre(client, cle, defaut) {
  try {
    const r = await client.query('SELECT valeur FROM "Parametres" WHERE cle = $1', [cle]);
    if (r.rows.length > 0) {
      const n = parseInt(r.rows[0].valeur, 10);
      if (!Number.isNaN(n)) return n;
    }
  } catch (e) {
    console.error('Erreur lecture parametre ' + cle + ':', e.message);
  }
  return defaut;
}

// Resout la cible en liste d'idUtilisateur (chaine).
// cible : 'tous' | 'actifs' | 'recents' | 'gare' | 'bloques' | 'selection'
// options : { gare, idUtilisateurs: [] }
async function resoudreIdUtilisateurs(client, cible, options) {
  switch (cible) {
    case 'tous': {
      const r = await client.query('SELECT "idUtilisateur" FROM "Utilisateurs"');
      return r.rows.map((x) => x.idUtilisateur);
    }
    case 'bloques': {
      const r = await client.query('SELECT "idUtilisateur" FROM "Utilisateurs" WHERE points = 0');
      return r.rows.map((x) => x.idUtilisateur);
    }
    case 'gare': {
      if (!options.gare) return [];
      const r = await client.query(
        'SELECT "idUtilisateur" FROM "Utilisateurs" WHERE residence = $1',
        [options.gare]
      );
      return r.rows.map((x) => x.idUtilisateur);
    }
    case 'actifs': {
      const mois = await lireParametreNombre(client, 'actifs_fenetre_mois', 3);
      const r = await client.query(
        `SELECT DISTINCT "idUtilisateur" FROM "Tickets"
         WHERE "idUtilisateur" IS NOT NULL
           AND "dateDeCreation" >= NOW() - ($1 || ' months')::interval`,
        [String(mois)]
      );
      return r.rows.map((x) => x.idUtilisateur);
    }
    case 'recents': {
      const jours = await lireParametreNombre(client, 'voyageursRecent', 7);
      const r = await client.query(
        `SELECT DISTINCT "idUtilisateur" FROM "Tickets"
         WHERE "idUtilisateur" IS NOT NULL
           AND "dateDeCreation" >= NOW() - ($1 || ' days')::interval`,
        [String(jours)]
      );
      return r.rows.map((x) => x.idUtilisateur);
    }
    case 'selection': {
      if (!Array.isArray(options.idUtilisateurs)) return [];
      return options.idUtilisateurs.map((x) => String(x));
    }
    default:
      return [];
  }
}

// POST /notif-diffusion/envoyer
// Body : { cible, titre, message, gare?, idUtilisateurs? }
router.post('/envoyer', async (req, res) => {
  const { cible, titre, message, gare, idUtilisateurs } = req.body || {};

  if (!cible || !titre || !message) {
    return res.status(200).json({ success: false, message: 'cible, titre et message requis' });
  }

  const client = await pool.connect();
  try {
    const ids = await resoudreIdUtilisateurs(client, cible, { gare, idUtilisateurs });
    if (ids.length === 0) {
      return res.status(200).json({ success: true, destinataires: 0, envoyes: 0, message: 'Aucun destinataire pour cette cible' });
    }

    const dest = await client.query(
      `SELECT token FROM "DeviceTokens"
       WHERE type_compte = 'utilisateur' AND id_compte = ANY($1)`,
      [ids]
    );
    const tokens = dest.rows.map((r) => r.token);

    if (tokens.length === 0) {
      return res.status(200).json({ success: true, destinataires: ids.length, envoyes: 0, message: 'Aucun appareil joignable' });
    }

    const corps = message.length > 240 ? message.substring(0, 237) + '...' : message;
    const r = await envoyerNotification(tokens, titre, corps, { type: 'diffusion' });

    return res.status(200).json({
      success: true,
      destinataires: ids.length,
      appareils: tokens.length,
      envoyes: r.succes,
      echecs: r.echecs,
      purges: r.invalidesPurges,
    });
  } catch (err) {
    console.error('Erreur notif diffusion:', err);
    return res.status(200).json({ success: false, message: 'Erreur serveur' });
  } finally {
    client.release();
  }
});

// POST /notif-diffusion/compter
// Body : { cible, gare?, idUtilisateurs? }
// Resout la cible et compte destinataires + appareils joignables, SANS envoyer.
router.post('/compter', async (req, res) => {
  const { cible, gare, idUtilisateurs } = req.body || {};

  if (!cible) {
    return res.status(200).json({ success: false, message: 'cible requise' });
  }

  const client = await pool.connect();
  try {
    const ids = await resoudreIdUtilisateurs(client, cible, { gare, idUtilisateurs });
    if (ids.length === 0) {
      return res.status(200).json({ success: true, destinataires: 0, appareils: 0 });
    }

    const dest = await client.query(
      `SELECT COUNT(*)::int AS n FROM "DeviceTokens"
       WHERE type_compte = 'utilisateur' AND id_compte = ANY($1)`,
      [ids]
    );

    return res.status(200).json({
      success: true,
      destinataires: ids.length,
      appareils: dest.rows[0].n,
    });
  } catch (err) {
    console.error('Erreur notif comptage:', err);
    return res.status(200).json({ success: false, message: 'Erreur serveur' });
  } finally {
    client.release();
  }
});

module.exports = router;
