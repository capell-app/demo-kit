<?php

declare(strict_types=1);

namespace Capell\DemoKit\Bridges\FormBuilder;

use Capell\Core\Facades\CapellCore;
use Capell\Core\Support\TableName;
use Capell\FormBuilder\Models\Form;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * The only place Demo Kit touches the optional Form Builder package.
 */
final class DemoFormBuilderBridge
{
    /** @return class-string<Model>|null */
    public static function formModel(): ?string
    {
        if (! CapellCore::isPackageAvailable('capell-app/form-builder')) {
            return null;
        }

        if (! Schema::hasTable(TableName::resolve('forms'))) {
            return null;
        }

        return Form::class;
    }
}
