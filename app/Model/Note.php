<?php

declare(strict_types=1);

namespace App\Model;

use Core\Model;

final class Note extends Model
{
    protected const string TABLE = 'note';
    protected const array FILLABLE = ['title', 'body'];
}
