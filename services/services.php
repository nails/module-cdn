<?php

return array(
    'services' => array(
        'Cdn' => function () {
            if (class_exists('\App\Cdn\Library\Cdn')) {
                return new \App\Cdn\Library\Cdn();
            } else {
                return new \Nails\Cdn\Library\Cdn();
            }
        }
    )
);
