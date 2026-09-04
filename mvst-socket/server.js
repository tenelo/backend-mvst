require('dotenv').config();
const express = require('express');
const http    = require('http');
const { Server } = require('socket.io');

const {
  rejoindreRoom,
  rejoindreRoomGare,
  chargerPlaces,
  choisirPlace,
  libererPlaces,
  gererDeconnexion,
  ticketScanne,
  placeAchetee,
} = require('./handlers/places');

const {
  rejoindreRoomSuggestionsUser,
  rejoindreRoomSuggestionsAdmin,
  nouvelleSuggestion,
  statutSuggestionChange,
  suggestionSupprimee,
  suggestionSupprimeeAdmin,
} = require('./handlers/suggestions');

const app    = express();
app.use(express.json());
const reinitialiserPin = require('./handlers/reinitialiser_pin');
const emitSynthese     = require('./handlers/emit_synthese');
const server = http.createServer(app);
const io     = new Server(server, {
  cors: {
    origin:  process.env.CORS_ORIGIN || '*',
    methods: ['GET', 'POST'],
  },
  pingTimeout:  10000,
  pingInterval: 5000,
});

app.use('/reinitialiser_pin', reinitialiserPin);
app.use('/emit-synthese', emitSynthese(io));

app.get('/health', (req, res) => {
  res.json({
    status:  'ok',
    service: 'MVST Socket.IO',
    uptime:  process.uptime(),
  });
});

io.on('connection', (socket) => {
  console.log(`\n🔌 Nouvelle connexion : ${socket.id}`);

  // ── Places ────────────────────────────────────────────────────────────
  socket.on('rejoindre_room', async (payload) => {
    await rejoindreRoom(socket, payload);
  });
  socket.on('rejoindre_room_gare', async (payload) => {
    await rejoindreRoomGare(socket, payload);
  });
  socket.on('charger_places', async (payload) => {
    await chargerPlaces(socket, payload);
  });
  socket.on('choisir_place', async (payload) => {
    await choisirPlace(socket, payload, io);
  });
  socket.on('liberer_places', async (payload) => {
    await libererPlaces(socket, payload, io);
  });
  socket.on('disconnect', async () => {
    await gererDeconnexion(socket, io);
  });

  // ── Tickets ───────────────────────────────────────────────────────────
  socket.on('ticket_scanne', async (payload) => {
    await ticketScanne(socket, payload, io);
  });
  socket.on('place_achetee', async (payload) => {
    await placeAchetee(socket, payload, io);
  });

  // ── Images ────────────────────────────────────────────────────────────
  socket.on('images_modifiees', () => {
    io.emit('images_modifiees');
  });

  // ── Suggestions ───────────────────────────────────────────────────────
  socket.on('rejoindre_suggestions_user', (payload) => {
    rejoindreRoomSuggestionsUser(socket, payload);
  });
  socket.on('rejoindre_suggestions_admin', () => {
    rejoindreRoomSuggestionsAdmin(socket);
  });
  socket.on('nouvelle_suggestion', (payload) => {
    nouvelleSuggestion(socket, payload, io);
  });
  socket.on('statut_suggestion_change', (payload) => {
    statutSuggestionChange(socket, payload, io);
  });
  socket.on('suggestion_supprimee', (payload) => {
    suggestionSupprimee(socket, payload, io);
  });
  socket.on('suggestion_supprimee_admin', (payload) => {
    suggestionSupprimeeAdmin(socket, payload, io);
  });
});

const PORT = process.env.PORT || 3000;
server.listen(PORT, () => {
  console.log(`\n🚀 MVST Socket.IO démarré sur le port ${PORT}`);
  console.log(`📡 Health check : http://localhost:${PORT}/health\n`);
});

// ─────────────────────────────────────────────────────────────────────────────
// PURGE AUTOMATIQUE DES PLACES TEMPORAIRES ABANDONNEES
// ─────────────────────────────────────────────────────────────────────────────
const PURGE_INTERVALLE_MS = 60 * 1000; // verifie toutes les minutes

async function lancerPurgePeriodique() {
  try {
    const reponse = await fetch('http://nginx-mvst/process_places_temporaires.php', {
      method: 'POST',
    });
    const resultat = await reponse.json();
    if (resultat.nettoyees) {
      console.log(`🧹 Purge auto: ${resultat.nettoyees} place(s) liberee(s) (delai: ${resultat.delaiMinutes} min)`);
    }
  } catch (err) {
    console.error('❌ Erreur purge automatique:', err.message);
  }
}

lancerPurgePeriodique();
setInterval(lancerPurgePeriodique, PURGE_INTERVALLE_MS);

// ─────────────────────────────────────────────────────────────────────────────
// NETTOYAGE QUOTIDIEN DES DEPARTS VIDES (une fois par jour, heure configurable)
// ─────────────────────────────────────────────────────────────────────────────
const VERIF_NETTOYAGE_DEPARTS_MS = 5 * 60 * 1000; // verifie toutes les 5 min

// Conteneur en UTC, qui correspond a l'heure locale Cote d'Ivoire (UTC+0,
// pas de changement d'heure saisonnier).
const NETTOYAGE_DEPARTS_HEURE_DEFAUT = '00:10'; // repli si Parametres illisible

let dernierNettoyageDepartsJour = null; // 'YYYY-MM-DD' du dernier nettoyage effectue

// Lit l'heure cible depuis Parametres (via Laravel, le Node n'accede pas a
// Postgres directement) -- modifiable a chaud par un simple UPDATE SQL, sans
// toucher au code. Best-effort strict : toute erreur retombe sur le repli en
// dur, jamais de plantage.
async function recupererHeureNettoyageDeparts() {
  try {
    const reponse = await fetch('http://nginx-mvst/config_nettoyage_departs.php');
    const resultat = await reponse.json();
    if (resultat.success && typeof resultat.heure === 'string') {
      return resultat.heure;
    }
  } catch (err) {
    console.error('❌ Erreur lecture config nettoyage departs (repli '+NETTOYAGE_DEPARTS_HEURE_DEFAUT+'):', err.message);
  }
  return NETTOYAGE_DEPARTS_HEURE_DEFAUT;
}

async function verifierNettoyageDepartsQuotidien() {
  const maintenant = new Date();
  const jourCourant = maintenant.toISOString().slice(0, 10);

  if (dernierNettoyageDepartsJour === jourCourant) {
    return; // deja nettoye aujourd'hui
  }

  const heureCible = await recupererHeureNettoyageDeparts();
  const heureCourante = maintenant.toTimeString().slice(0, 5); // 'HH:MM'
  if (heureCourante < heureCible) {
    return; // creneau cible pas encore atteint
  }

  // heureCourante >= cible (comparaison lexicographique valide sur "HH:MM")
  // ET pas deja fait aujourd'hui -> declenche. Au plus ~5 min de retard sur
  // la cible exacte, selon l'alignement du setInterval avec l'horloge --
  // une egalite stricte sur "HH:MM" pourrait manquer le creneau si le
  // sondage toutes les 5 min ne tombe pas exactement sur la bonne minute.
  try {
    const reponse = await fetch('http://nginx-mvst/process_departs_vides.php', {
      method: 'POST',
    });
    const resultat = await reponse.json();
    dernierNettoyageDepartsJour = jourCourant;
    console.log(`🧹 Nettoyage departs vides (cible ${heureCible}): ${resultat.supprimes ?? 0} depart(s) supprime(s)`);
  } catch (err) {
    console.error('❌ Erreur nettoyage departs vides:', err.message);
  }
}

setInterval(verifierNettoyageDepartsQuotidien, VERIF_NETTOYAGE_DEPARTS_MS);
