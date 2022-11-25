<?php

namespace App\Core\Extended;

use Illuminate\Contracts\Support\Arrayable;

class ResponseFactory extends \Illuminate\Routing\ResponseFactory
{
    /**
     * {@inheritdoc}
     */
    public function json(
        $data = [],
        $status = 200,
        array $headers = [],
        $options = 0
    ) {
        if ($data instanceof Arrayable) {
            $data = $data->toArray();
        }

        return new JsonResponse($data, $status, $headers, $options);
    }
}
