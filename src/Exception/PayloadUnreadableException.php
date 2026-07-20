<?php

declare(strict_types=1);

namespace App\Exception;

/**
 * Le fichier n'a pas pu etre lu : chemin faux, droits, disque. Une erreur
 * d'appel ou d'environnement — pas une donnee client invalide.
 */
class PayloadUnreadableException extends \RuntimeException
{
}
