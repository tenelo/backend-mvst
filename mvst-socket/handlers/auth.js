// mvst-socket/handlers/auth.js
//
// Middleware d'authentification SOUPLE du handshake Socket.IO (lot 1).
// Identifie l'émetteur quand c'est possible, sans JAMAIS refuser une
// connexion. Token lu dans socket.handshake.auth.token, validé via GET /me
// (même endpoint interne que l'app REST), résultat déposé dans
// socket.data.auth. Client sans token -> socket.data.auth = null, connexion
// acceptée. Aucune décision d'autorisation ici (viendra aux événements).

const URL_ME = 'http://nginx-mvst/me';
const TIMEOUT_MS = 4000;

async function resoudreIdentite(token) {
  const ctrl = new AbortController();
  const t = setTimeout(() => ctrl.abort(), TIMEOUT_MS);
  try {
    const reponse = await fetch(URL_ME, {
      method: 'GET',
      headers: { Authorization: `Bearer ${token}` },
      signal: ctrl.signal,
    });
    const data = await reponse.json();
    if (!data || data.success !== true || !data.utilisateur) {
      return null;
    }
    const u = data.utilisateur;
    return {
      id:            u.id ?? null,
      idUtilisateur: u.idUtilisateur ?? null,
      role:          u.role ?? null,   // présent seulement pour un Admin
      gare:          u.gare ?? null,   // idem
    };
  } catch (err) {
    console.error('⚠️ Auth socket: validation token échouée:', err.message);
    return null;
  } finally {
    clearTimeout(t);
  }
}

// Exécuté une fois par connexion, avant io.on('connection').
// TOUJOURS next() sans erreur -> handshake souple.
function middlewareAuth() {
  return async (socket, next) => {
    socket.data.auth = null;
    try {
      const token =
        socket.handshake?.query?.token ||
        socket.handshake?.auth?.token ||
        socket.handshake?.headers?.['x-auth-token'];
      if (token && typeof token === 'string') {
        const identite = await resoudreIdentite(token);
        if (identite) {
          socket.data.auth = identite;
          console.log(
            `🔐 Socket ${socket.id} authentifié : ${identite.role || 'utilisateur'}` +
            (identite.gare ? ` (${identite.gare})` : '')
          );
        }
      }
    } catch (err) {
      console.error('⚠️ Auth socket (middleware):', err.message);
    }
    next();
  };
}

module.exports = { middlewareAuth };
