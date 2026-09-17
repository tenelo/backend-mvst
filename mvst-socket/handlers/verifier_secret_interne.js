// mvst-socket/handlers/verifier_secret_interne.js
//
// Middleware Express : protege les routes HTTP internes appelees UNIQUEMENT
// par Laravel (jamais par les apps Flutter) via un secret partage. Compare
// le header X-Internal-Secret a process.env.INTERNAL_SOCKET_SECRET.
// Absent ou different -> 403. Ne s'applique PAS au socket ni a /health.
function verifierSecretInterne(req, res, next) {
  const secretAttendu = process.env.INTERNAL_SOCKET_SECRET;
  const secretRecu = req.headers['x-internal-secret'];

  if (!secretAttendu || secretRecu !== secretAttendu) {
    return res.status(403).json({ success: false, message: 'Interdit' });
  }

  next();
}

module.exports = { verifierSecretInterne };
