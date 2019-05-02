# CDN Module for Nails

![license](https://img.shields.io/badge/license-MIT-green.svg)
[![CircleCI branch](https://img.shields.io/circleci/project/github/nails/module-cdn.svg)](https://circleci.com/gh/nails/module-cdn)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/nails/module-cdn/badges/quality-score.png)](https://scrutinizer-ci.com/g/nails/module-cdn)
[![Join the chat on Slack!](https://now-examples-slackin-rayibnpwqe.now.sh/badge.svg)](https://nails-app.slack.com/shared_invite/MTg1NDcyNjI0ODcxLTE0OTUwMzA1NTYtYTZhZjc5YjExMQ)

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
