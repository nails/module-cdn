# Image Transformation: Security
> Documentation is a WIP.

Whilst image manipulation via the URL is incredibly useful it is quite dangerous to leave this open. An abuser could quite quickly overwhelm the server by generating many variations of an image.


## Restricting Image Transformation

All components, including the application, must explicitly announce the image dimensions they wish the CDN to honour; this is done in each component's `composer.json` file:

```json
{
    "extra": {
        "nails": {
            "data": {
                "nailsapp/module-cdn": {
                    "permitted-image-dimensions": [
                        "120x120",
                        "250x250"
                    }
                }
            }
        }
    }
}
```

The array will accept values which match the following regexes:

- `/^\dx\d$/i`
- `/^\d$/`

You may also pass an array, where the first element is the width as an integer, and the second is the height as an integer.

If a value cannot be parsed then a `\Nails\Cdn\Exception\PermittedDimensionException` exception will be thrown. This exception will also be thrown if you attempt to generate, or visit, a URL which results in an invalid dimension being requested.

> Note; when in PRODUCTION an exception will **not** be thrown when visiting an invalid URL, instead the user will receive a 404.



## Disabling Restrictions

If you absoloutely must disable this security measure, then you may do so by setting the module's  `allowDangerousImageTransformation` service property to `true`.
