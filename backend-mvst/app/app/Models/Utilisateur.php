<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

/**
 * Pointe sur la table existante "Utilisateurs" (casse exacte, cf. schema
 * PostgreSQL). Aucune table creee/modifiee par ce modele.
 *
 * $timestamps desactive : la table a "dateDeCreation", pas les colonnes
 * "created_at"/"updated_at" que Eloquent gererait automatiquement sinon.
 *
 * "pin" masque de toute serialisation JSON par defaut (Model::toArray()/
 * toJson()) ; les controleurs d'auth construisent de toute facon leurs
 * reponses a la main sans jamais lire cette colonne dedans.
 */
class Utilisateur extends Model
{
    use HasApiTokens;

    protected $table = 'Utilisateurs';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $hidden = ['pin'];
}
