const ROOM_ADMINS = 'room_suggestions_admins';

function rejoindreRoomSuggestionsUser(socket, payload) {
  const { idutilisateur } = payload;
  if (!idutilisateur) return;
  const room = `room_suggestions_user_${idutilisateur}`;
  socket.join(room);
}

function rejoindreRoomSuggestionsAdmin(socket) {
  socket.join(ROOM_ADMINS);
  console.log(`👔 Admin ${socket.id} rejoint ${ROOM_ADMINS}`);
}

function nouvelleSuggestion(socket, payload, io) {
  io.to(ROOM_ADMINS).emit('nouvelle_suggestion', payload);
  console.log(`📩 Nouvelle suggestion de ${payload.idutilisateur}`);
}

function statutSuggestionChange(socket, payload, io) {
  const { idutilisateur, id, statut } = payload;
  if (!idutilisateur) return;
  const room = `room_suggestions_user_${idutilisateur}`;
  io.to(room).emit('statut_suggestion_change', { id, statut });
  console.log(`🔄 Statut suggestion #${id} → ${statut}`);
}

function suggestionSupprimee(socket, payload, io) {
  io.to(ROOM_ADMINS).emit('suggestion_supprimee', payload);
  console.log(`🗑️ Suggestion #${payload.id} supprimée`);
}

function suggestionSupprimeeAdmin(socket, payload, io) {
  const { id, idutilisateur } = payload;
  if (idutilisateur) {
    const room = `room_suggestions_user_${idutilisateur}`;
    io.to(room).emit('suggestion_supprimee_admin', { id });
  }
  console.log(`🗑️ Admin supprime suggestion #${id}`);
}

module.exports = {
  rejoindreRoomSuggestionsUser,
  rejoindreRoomSuggestionsAdmin,
  nouvelleSuggestion,
  statutSuggestionChange,
  suggestionSupprimee,
  suggestionSupprimeeAdmin,
};
