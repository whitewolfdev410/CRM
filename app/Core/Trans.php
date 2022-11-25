<?php

namespace App\Core;

use Illuminate\Contracts\Container\Container;

class Trans
{
    protected $texts;

    public function __construct(Container $app)
    {
        $this->app = $app;
    }

    protected function getLocale()
    {
        return 'en';
        // config('app.locale');
        // $this->app->getLocale();
    }

    protected function loadLanguage()
    {
        $locale = $this->getLocale();

        if (!isset($this->texts[$locale])) {
            $file = resource_path("lang/{$locale}/messages.php");

            if (file_exists($file)) {
                $this->texts[$locale] = require $file;
            }
        }
    }

    /**
     * Get translated column name
     *
     * @param string $label
     *
     * @return string
     */
    public function getColumn($label)
    {
        // @todo
        return $label;
    }

    /**
     * Get translated url anchor text
     *
     * @param string $label
     * @param string $module
     *
     * @return string
     */
    public function getAnchor($label, $module = '')
    {
        // @todo
        return $label;
    }

    /**
     * Get translated text
     *
     * @param string $label
     * @param array $params
     *
     *
     * @return string
     */
    public function get($label, array $params = [])
    {
        $this->loadLanguage();

        $texts = $this->texts[$this->getLocale()];

        if (isset($texts[$label])) {
            if ($params) {
                foreach ($params as $key => $val) {
                    $texts[$label] = str_replace(
                        ':' . $key,
                        $val,
                        $texts[$label]
                    );
                }
            }

            return $texts[$label];
        }

        // @todo
        return $label;
    }
}
