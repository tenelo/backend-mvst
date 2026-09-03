const express = require('express');

// Contrairement a reinitialiser_pin.js (aucune dependance injectee, juste
// require('firebase-admin') en dur), ce routeur a besoin de l'instance io
// pour diffuser aux rooms -- pas de precedent direct dans ce fichier a
// calquer, donc factory function standard Express : module.exports est une
// fonction qui prend io et renvoie le router, montee via
// app.use('/emit-synthese', emitSynthese(io)) dans server.js.
module.exports = (io) => {
  const router = express.Router();

  // POST /emit-synthese
  // Route HTTP entrante appelee par Laravel apres un scan/vente, pour
  // diffuser un signal LEGER (juste { gare }, pas de chiffres) a tous les
  // clients ayant rejoint la room de cette gare. L'app recharge ensuite
  // elle-meme synthese_gare.php cote HTTP.
  router.post('/', (req, res) => {
    const { gare } = req.body;

    if (!gare || typeof gare !== 'string') {
      return res.status(400).json({ success: false, message: 'Paramètre gare manquant' });
    }

    io.to(`gare_${gare}`).emit('synthese_maj', { gare });

    return res.json({ success: true });
  });

  return router;
};
