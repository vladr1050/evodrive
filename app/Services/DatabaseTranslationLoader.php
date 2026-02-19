<?php

namespace App\Services;

use App\Models\Translation;
use Illuminate\Contracts\Translation\Loader;

class DatabaseTranslationLoader implements Loader
{
    public function __construct(
        protected Loader $fileLoader
    ) {}

    public function load($locale, $group, $namespace = null)
    {
        $fileTranslations = $this->fileLoader->load($locale, $group, $namespace);

        if (($namespace ?? '*') !== '*' || ! in_array($group, ['ui', 'apply'], true)) {
            return $fileTranslations;
        }

        $dbTranslations = Translation::where('group', $group)
            ->get()
            ->mapWithKeys(fn (Translation $t) => [$t->key => $t->get($locale) ?? $t->en])
            ->filter()
            ->all();

        return array_merge($fileTranslations, $dbTranslations);
    }

    public function addNamespace($namespace, $hint)
    {
        $this->fileLoader->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path)
    {
        $this->fileLoader->addJsonPath($path);
    }

    public function namespaces()
    {
        return $this->fileLoader->namespaces();
    }
}
