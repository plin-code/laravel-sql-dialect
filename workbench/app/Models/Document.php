<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $title
 * @property string|null $issued_on
 * @property string|null $recorded_at
 * @property string|null $from
 */
class Document extends Model
{
    public $timestamps = false;

    protected $table = 'documents';

    protected $guarded = [];
}
