<?php

return array(
    'services' => array(
        'Cdn' => function () {
            return new \Nails\Cdn\Library\Cdn();
        }
    )
);
