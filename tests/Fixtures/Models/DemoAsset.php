<?php

declare(strict_types=1);

namespace Capell\DemoKit\Tests\Fixtures\Models;

use Capell\Core\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Override;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class DemoAsset extends Model implements HasMedia
{
    use HasFactory;
    use HasFactory;
    use HasTranslations;
    use InteractsWithMedia;

    protected $guarded = [];

    #[Override]
    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }
}
