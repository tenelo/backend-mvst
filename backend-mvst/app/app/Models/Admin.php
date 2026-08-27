<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

/**
 * Pointe sur la table existante "Admins" (casse exacte). Aucune table
 * creee/modifiee par ce modele. Memes remarques que Utilisateur.php sur
 * $timestamps et $hidden.
 */
class Admin extends Model
{
    use HasApiTokens;

    protected $table = 'Admins';

    protected $primaryKey = 'id';

    public $timestamps = false;

    protected $hidden = ['pin'];
}
