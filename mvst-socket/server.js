require('dotenv').config();
const express = require('express');
const http    = require('http');
const { Server } = require('socket.io');

const {
  rejoindreRoom,
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
