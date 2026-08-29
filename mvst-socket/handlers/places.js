const pool = require('../db');

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

    const result = await client.query(
      `SELECT place FROM "Tickets" WHERE "documentId" = $1`,
      [documentId]
    );

    const placesOccupees = result.rows.map(r => r.place);

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

    await client.query('BEGIN');

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

    if (result.rows.length > 0) {
      await client.query(
        `UPDATE "Departs" SET "placesChoisies" = $1 WHERE "documentId" = $2`,
        [nouvellesPlaces, documentId]
      );
    } else {
      await client.query(
        `INSERT INTO "Departs"
          ("documentId", "dateDeDepart", "heureDeDepart", depart, destination,
           "moisAnnee", annee, "placesChoisies", mois, "idDesDepartsParLigne")
         VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10)`,
        [documentId, date, heure, depart, destination,
         moisAnnee, annee, nouvellesPlaces, mois, idDesDepartsParLigne]
      );
    }

    await client.query(
      `INSERT INTO "PlacesTemporaires" ("documentId", places) VALUES ($1, $2)`,
      [documentId, numeroDePlace]
    );

    await client.query('COMMIT');
    console.log(`✅ Place ${numeroDePlace} réservée dans ${documentId}`);

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
