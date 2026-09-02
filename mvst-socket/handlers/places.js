const pool = require('../db');

const MOIS_FR = {
  janvier: '01', février: '02', mars: '03', avril: '04', mai: '05', juin: '06',
  juillet: '07', août: '08', septembre: '09', octobre: '10', novembre: '11', décembre: '12',
};

// Jumelle du parsing SQL de la migration dateVoyage (mois FR -> numero,
// meme table de correspondance). Pure, synchrone, ne throw JAMAIS : tout
// cas hors-format retourne null (colonne "dateVoyage" nullable). Pas de
// new Date() (fuseaux) : formatage manuel de la chaine "YYYY-MM-DD".
function parseDateFrToISO(dateFr) {
  if (typeof dateFr !== 'string') {
    return null;
  }
  const parts = dateFr.split('_');
  if (parts.length !== 4) {
    return null;
  }
  const [, jour, mois, annee] = parts;
  if (!/^[0-9]{1,2}$/.test(jour) || !/^[0-9]{4}$/.test(annee)) {
    return null;
  }
  const moisNum = MOIS_FR[mois.toLowerCase()];
  if (!moisNum) {
    return null;
  }
  const jourPad = jour.padStart(2, '0');
  return `${annee}-${moisNum}-${jourPad}`;
}

function construireDocumentId(depart, destination, date, heure) {
  return `${depart}-${destination}_${date}_${heure}_h`;
}

function construireNomRoom(documentId) {
  return `room_${documentId}`;
}

// ─────────────────────────────────────────────────────────────────────────────
// ÉVÉNEMENT : rejoindreRoom
// ─────────────────────────────────────────────────────────────────────────────
async function rejoindreRoom(socket, payload) {
  try {
    const { depart, destination, date, heure } = payload;
    const documentId = construireDocumentId(depart, destination, date, heure);
    const nomRoom    = construireNomRoom(documentId);
    socket.join(nomRoom);
    socket.data.documentId  = documentId;
    socket.data.nomRoom     = nomRoom;
    socket.data.depart      = depart;
    socket.data.destination = destination;
    socket.data.date        = date;
    socket.data.heure       = heure;
    console.log(`👤 Socket ${socket.id} a rejoint : ${nomRoom}`);
    socket.emit('room_rejointe', { documentId, nomRoom });
  } catch (err) {
    console.error('❌ Erreur rejoindreRoom:', err.message);
    socket.emit('erreur', { message: 'Impossible de rejoindre la room' });
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// ÉVÉNEMENT : chargerPlaces
// Lit uniquement les tickets réellement achetés depuis la table Tickets
// ─────────────────────────────────────────────────────────────────────────────
async function chargerPlaces(socket, payload) {
  const client = await pool.connect();
  try {
    const { documentId } = payload;

    const ticketsResult = await client.query(
      `SELECT place FROM "Tickets" WHERE "documentId" = $1 AND statut = 'valide'`,
      [documentId]
    );
    const placesVendues = ticketsResult.rows.map(r => r.place);

    const departResult = await client.query(
      `SELECT "placesChoisies" FROM "Departs" WHERE "documentId" = $1`,
      [documentId]
    );

    let placesEnCours = [];
    if (departResult.rows.length > 0 && departResult.rows[0].placesChoisies) {
      const decoded = JSON.parse(departResult.rows[0].placesChoisies);
      placesEnCours = Array.isArray(decoded) ? decoded : [];
    }

    const placesOccupees = [...new Set([...placesVendues, ...placesEnCours])];

    socket.emit('places_chargees', {
      success:        true,
      placesOccupees: placesOccupees,
      documentId:     documentId,
    });
    console.log(`📋 Places chargées pour ${documentId} : [${placesOccupees}]`);
  } catch (err) {
    console.error('❌ Erreur chargerPlaces:', err.message);
    socket.emit('places_chargees', { success: false, message: 'Erreur chargement' });
  } finally {
    client.release();
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// ÉVÉNEMENT : choisirPlace
// ─────────────────────────────────────────────────────────────────────────────
async function choisirPlace(socket, payload, io) {
  const client = await pool.connect();
  try {
    const { depart, destination, date, heure, mois, moisAnnee, annee, numeroDePlace } = payload;
    const documentId           = construireDocumentId(depart, destination, date, heure);
    const nomRoom              = construireNomRoom(documentId);
    const idDesDepartsParLigne = `${depart}_${date}_${heure}`;
    const dateVoyage           = parseDateFrToISO(date);

    await client.query('BEGIN');

    // Garantit l'existence de la ligne Departs avant le verrou, de facon
    // atomique et sans erreur en cas de concurrence sur la toute premiere
    // reservation d'un trajet (plusieurs clients simultanes sur un trajet
    // neuf ne se serialisaient pas via FOR UPDATE, qui ne verrouille rien
    // tant qu'aucune ligne n'existe — corrige ici).
    await client.query(
      `INSERT INTO "Departs"
        ("documentId", "dateDeDepart", "heureDeDepart", depart, destination,
         "moisAnnee", annee, "placesChoisies", mois, "idDesDepartsParLigne", "dateVoyage")
       VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11)
       ON CONFLICT ("documentId") DO NOTHING`,
      [documentId, date, heure, depart, destination,
       moisAnnee, annee, '[]', mois, idDesDepartsParLigne, dateVoyage]
    );

    const result = await client.query(
      `SELECT "placesChoisies" FROM "Departs" WHERE "documentId" = $1 FOR UPDATE`,
      [documentId]
    );

    let placesActuelles = [];
    if (result.rows.length > 0 && result.rows[0].placesChoisies) {
      const decoded = JSON.parse(result.rows[0].placesChoisies);
      placesActuelles = Array.isArray(decoded) ? decoded : [];
    }

    if (placesActuelles.includes(numeroDePlace)) {
      await client.query('ROLLBACK');
      socket.emit('place_echec', {
        success:       false,
        numeroDePlace: numeroDePlace,
        message:       "Cette place vient d'être prise",
      });
      console.log(`❌ Place ${numeroDePlace} déjà prise dans ${documentId}`);
      return;
    }

    placesActuelles.push(numeroDePlace);
    const nouvellesPlaces = JSON.stringify(placesActuelles);

    await client.query(
      `UPDATE "Departs" SET "placesChoisies" = $1 WHERE "documentId" = $2`,
      [nouvellesPlaces, documentId]
    );

    await client.query(
      `INSERT INTO "PlacesTemporaires" ("documentId", places) VALUES ($1, $2)`,
      [documentId, numeroDePlace]
    );

    await client.query('COMMIT');
    console.log(`✅ Place ${numeroDePlace} réservée dans ${documentId}`);

    if (!socket.data.placesChoisies) {
      socket.data.placesChoisies = [];
    }
    socket.data.placesChoisies.push(numeroDePlace);

    socket.emit('place_confirmee', {
      success:       true,
      numeroDePlace: numeroDePlace,
      message:       'succès',
    });

    socket.to(nomRoom).emit('place_prise', {
      numeroDePlace: numeroDePlace,
      documentId:    documentId,
    });

  } catch (err) {
    await client.query('ROLLBACK');
    console.error('❌ Erreur choisirPlace:', err.message);
    socket.emit('place_echec', {
      success:       false,
      numeroDePlace: payload.numeroDePlace,
      message:       'Erreur serveur',
    });
  } finally {
    client.release();
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// ÉVÉNEMENT : libererPlaces
// ─────────────────────────────────────────────────────────────────────────────
async function libererPlaces(socket, payload, io) {
  const client = await pool.connect();
  let documentId;
  let numerosDePlace;
  try {
    ({ numerosDePlace } = payload);
    const { depart, destination, date, heure } = payload;
    documentId = construireDocumentId(depart, destination, date, heure);
    const nomRoom = construireNomRoom(documentId);

    await client.query('BEGIN');

    const result = await client.query(
      `SELECT "placesChoisies" FROM "Departs" WHERE "documentId" = $1 FOR UPDATE`,
      [documentId]
    );

    if (result.rows.length === 0) {
      await client.query('ROLLBACK');
      socket.emit('liberation_echec', {
        documentId,
        numerosDePlace,
        raison: 'trajet introuvable',
      });
      return;
    }

    const ticketsResult = await client.query(
      `SELECT place FROM "Tickets" WHERE "documentId" = $1 AND place = ANY($2::int[]) AND statut = 'valide'`,
      [documentId, numerosDePlace]
    );
    const placesVendues  = ticketsResult.rows.map(r => r.place);
    const placesALiberer = numerosDePlace.filter(p => !placesVendues.includes(p));

    if (placesVendues.length > 0) {
      console.warn(`⚠️ libererPlaces: places [${placesVendues}] ignorées dans ${documentId} — Ticket valide déjà existant`);
    }

    if (placesALiberer.length === 0) {
      await client.query('ROLLBACK');
      return;
    }

    let placesActuelles = [];
    if (result.rows[0].placesChoisies) {
      const decoded = JSON.parse(result.rows[0].placesChoisies);
      placesActuelles = Array.isArray(decoded) ? decoded : [];
    }

    const placesRestantes = placesActuelles.filter(p => !placesALiberer.includes(p));
    const nouvellesPlaces = JSON.stringify(placesRestantes);

    await client.query(
      `UPDATE "Departs" SET "placesChoisies" = $1 WHERE "documentId" = $2`,
      [nouvellesPlaces, documentId]
    );

    for (const place of placesALiberer) {
      await client.query(
        `DELETE FROM "PlacesTemporaires" WHERE "documentId" = $1 AND places = $2`,
        [documentId, place]
      );
    }

    await client.query('COMMIT');
    console.log(`🔓 Places [${placesALiberer}] libérées dans ${documentId}`);

    if (socket.data.placesChoisies) {
      socket.data.placesChoisies = socket.data.placesChoisies.filter(p => !placesALiberer.includes(p));
    }

    io.to(nomRoom).emit('place_liberee', {
      numerosDePlace: placesALiberer,
      documentId:     documentId,
    });

  } catch (err) {
    await client.query('ROLLBACK');
    console.error('❌ Erreur libererPlaces:', err.message);
    socket.emit('liberation_echec', {
      documentId,
      numerosDePlace,
      raison: 'erreur serveur',
    });
  } finally {
    client.release();
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// ÉVÉNEMENT : gererDeconnexion
// ─────────────────────────────────────────────────────────────────────────────
async function gererDeconnexion(socket, io) {
  console.log(`👋 Socket déconnecté : ${socket.id}`);

  const placesChoisies = socket.data.placesChoisies;
  const documentId     = socket.data.documentId;
  const nomRoom        = socket.data.nomRoom;

  if (!placesChoisies || placesChoisies.length === 0 || !documentId) {
    return;
  }

  const client = await pool.connect();
  try {
    await client.query('BEGIN');

    const result = await client.query(
      `SELECT "placesChoisies" FROM "Departs" WHERE "documentId" = $1 FOR UPDATE`,
      [documentId]
    );

    if (result.rows.length === 0) {
      await client.query('ROLLBACK');
      return;
    }

    const ticketsResult = await client.query(
      `SELECT place FROM "Tickets" WHERE "documentId" = $1 AND place = ANY($2::int[]) AND statut = 'valide'`,
      [documentId, placesChoisies]
    );
    const placesVendues  = ticketsResult.rows.map(r => r.place);
    const placesALiberer = placesChoisies.filter(p => !placesVendues.includes(p));

    if (placesALiberer.length === 0) {
      await client.query('ROLLBACK');
      return;
    }

    let placesActuelles = [];
    if (result.rows[0].placesChoisies) {
      const decoded = JSON.parse(result.rows[0].placesChoisies);
      placesActuelles = Array.isArray(decoded) ? decoded : [];
    }

    const placesRestantes = placesActuelles.filter(p => !placesALiberer.includes(p));
    const nouvellesPlaces = JSON.stringify(placesRestantes);

    await client.query(
      `UPDATE "Departs" SET "placesChoisies" = $1 WHERE "documentId" = $2`,
      [nouvellesPlaces, documentId]
    );

    for (const place of placesALiberer) {
      await client.query(
        `DELETE FROM "PlacesTemporaires" WHERE "documentId" = $1 AND places = $2`,
        [documentId, place]
      );
    }

    await client.query('COMMIT');
    console.log(`🔓 Places [${placesALiberer}] libérées suite à la déconnexion de ${socket.id} dans ${documentId}`);

    if (nomRoom) {
      io.to(nomRoom).emit('place_liberee', {
        numerosDePlace: placesALiberer,
        documentId:     documentId,
      });
    }
  } catch (err) {
    await client.query('ROLLBACK');
    console.error('❌ Erreur gererDeconnexion:', err.message);
  } finally {
    client.release();
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// ÉVÉNEMENT : ticket_scanne
// App Admin l'envoie après avoir scanné un ticket avec succès
// Payload : { documentId, idUtilisateur, place }
// Broadcast à tous les utilisateurs de la Room → QR code change de couleur
// ─────────────────────────────────────────────────────────────────────────────
async function ticketScanne(socket, payload, io) {
  try {
    const { documentId, idUtilisateur, place } = payload;
    const nomRoom = construireNomRoom(documentId);
    io.to(nomRoom).emit('ticket_valide', {
      documentId:    documentId,
      idUtilisateur: idUtilisateur,
      place:         place,
    });
  } catch (err) {
    socket.emit('erreur', { message: 'Erreur serveur' });
  }
}

// ─────────────────────────────────────────────────────────────────────────────
// ÉVÉNEMENT : place_achetee
// Émis par l'app utilisateur après finalisation d'un achat
// Payload : { documentId, depart, destination, date, heure }
// Broadcast à tous les admins connectés à la Room → liste passagers mise à jour
// ─────────────────────────────────────────────────────────────────────────────
async function placeAchetee(socket, payload, io) {
  try {
    const { documentId, depart, destination, date, heure } = payload;
    const nomRoom = construireNomRoom(documentId);
    io.to(nomRoom).emit('liste_mise_a_jour', {
      documentId,
      depart,
      destination,
      date,
      heure,
    });
  } catch (err) {
    socket.emit('erreur', { message: 'Erreur serveur' });
  }
}

module.exports = {
  rejoindreRoom,
  chargerPlaces,
  choisirPlace,
  libererPlaces,
  gererDeconnexion,
  ticketScanne,
  placeAchetee,
};
