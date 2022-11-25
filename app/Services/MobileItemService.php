<?php

namespace App\Services;

use Illuminate\Support\Str;
use App\Core\Trans;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;

class MobileItemService
{
    /**
     * @var Container
     */
    protected $app;

    /**
     * @var Trans
     */
    protected $trans;

    /**
     * Type of service
     *
     * @var null|string
     */
    protected $type = null;

    /**
     * Initialize class
     *
     * @param Container $app
     * @param Trans $trans
     * @param $type
     */
    public function __construct(Container $app, Trans $trans, $type)
    {
        $this->app = $app;
        $this->trans = $trans;
        $this->type = $type;
    }

    /**
     * Get buttons for mobile item
     *
     * @param Model $item
     *
     * @return array
     */
    protected function getButtons($item)
    {
        $buttons =
            $this->app->config->get('mobile_' . $this->type . '.item.menu', []);

        /* @todo add using permissions in future to display only those buttons
         * that user is permitted
         */

        $buttonsMenu = [];
        foreach ($buttons as $ind => $buttonsList) {
            $c = count($buttonsMenu);
            foreach ($buttonsList as $index => $button) {
                // button not enabled, go to next one
                if ($button['enabled'] === false) {
                    continue;
                }

                // changing all method starting with {url} to app url
                if (Str::startsWith($button['action']['method'], '{url}')) {
                    $buttonsList[$index]['action']['method'] =
                        str_replace(
                            '{url}',
                            $this->app->config->get('app.url'),
                            $button['action']['method']
                        );
                }

                // changing all web_view_url starting with {url} to app url
                if (isset($button['action']['web_view_url']) &&
                    Str::startsWith($button['action']['web_view_url'], '{url}')
                ) {
                    $buttonsList[$index]['action']['web_view_url']
                        = str_replace(
                            '{url}',
                            $this->app->config->get('app.url'),
                            $button['action']['web_view_url']
                        );
                }

                // button processing (button logic, adding params etc)
                $buttonsList[$index] =
                    $this->processButton($item, $index, $buttonsList[$index]);

                // button might be disabled in processButton so let's verify it
                if ($buttonsList[$index]['enabled'] === false) {
                    continue;
                }

                // translate menu text
                $buttonsList[$index]['text'] =
                    $this->trans->get($buttonsList[$index]['text_key']);

                // removing some data from output
                unset($buttonsList[$index]['permissions']);
                unset($buttonsList[$index]['text_key']);
                unset($buttonsList[$index]['enabled']);

                // now we want to change also some strings in params
                $buttonsList[$index] =
                    $this->modifyParameters($buttonsList[$index], $item);

                // now we want to set up colors
                if (isset($buttonsList[$index]['selector'])) {
                    // button has own colors - we use it
                    $colors = $buttonsList[$index]['selector'];
                } else {
                    // otherwise we use buttons default colors
                    $colors =
                        $this->app->config->get('mobile.buttons_defaults.selector');
                }

                if ($buttonsList[$index]['active']) {
                    // button is active - we set active color scheme
                    $buttonsList[$index]['selector'] = $colors['active'];
                } else {
                    // button is not active - we set inactive color scheme
                    $buttonsList[$index]['selector'] = $colors['inactive'];
                }

                $buttonsMenu[$c][] = $buttonsList[$index];
            }

            // no elements in row - remove the row
            if (empty($buttonsMenu)) {
                unset($buttonsMenu[$c]);
            }
        }

        unset($buttonsList);

        return $buttonsMenu;
    }

    /**
     * Modify button parameters
     *
     * @param array $button
     * @param Model $item
     * @return array
     */
    protected function modifyParameters(array $button, $item)
    {
        // verify if action and action.params exist for button
        if (!array_key_exists('action', $button) ||
            !array_key_exists('params', $button['action'])
        ) {
            return $button;
        }

        // now lets modify parameters
        foreach ($button['action']['params'] as $key => $element) {
            foreach ($element as $k => $v) {
                if (is_string($v)) {
                    // we change it only for strings because we don't want
                    // integers or booleans become strings
                    $button['action']['params'][$key][$k] =
                        str_replace('{item_id}', $item->getId(), $v);

                    // if string is equal to item_id we want to cast it to int
                    if ($v == '{item_id}') {
                        $button['action']['params'][$key][$k] =
                            (int)$button['action']['params'][$key][$k];
                    }
                }
            }
        }

        return $button;
    }

    /**
     * Process button - by default it does nothing, only return not modified
     * button but in child classes it might be used to add logic to button or
     * extra data
     *
     * @param string $index
     * @param array $button
     * @return array
     */
    protected function processButton($item, $index, array $button)
    {
        return $button;
    }

    /**
     * Get item title
     *
     * @param array $changers
     *
     * @return string
     */
    protected function getTitle(array $changers)
    {
        $title = $this->app->config->get('mobile_' . $this->type .
            '.item.title_key');

        return $this->trans->get($title, $changers);
    }
}
