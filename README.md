# CDN Module for Nails

![license](https://img.shields.io/badge/license-MIT-green.svg)
[![tests](https://github.com/nails/module-cdn/actions/workflows/build_and_test.yml/badge.svg)](https://github.com/nails/module-cdn/actions)

This is the CDN module for Nails, it brings simple file storage, management, and image manipulation to the app with support for popular object storage (e.g. S3) and distributed edges (e.g. CloudFront).

-

Note: If you are using the `crop` functionality of this module it is recommended to use @hellopablo's fork of PHPThumb. There is a bug in the original package which causes black lines to be rendered at the edge of images when cropped to certain dimensions.

To use @hellopablo's fork you must alias the package at the root level `composer.json` (i.e., your project's `composer.json`) file.

```json
"repositories": [{
    "type": "vcs",
    "url": "https://github.com/hellopablo/PHPThumb"
}]
```
