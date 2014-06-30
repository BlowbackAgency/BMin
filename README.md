# [BMin](https://github.com/BlowbackDesign/BMin)

__Super simple JS/CSS/LESS compiler for PHP5__

## Methods

- `$a->styles($group, $fileset, $options)`
- `$a->scripts($group, $fileset, $options)`
- `$a->delete($type, $group)`
- `$a->set($key, $value)`
- `$a->debug()`

## Options

Name       | Type   | Default       | Description
---------- | ------ | ------------- | -----------
`live`     | bool   | `false`       | On live mode bmin skips all file methods and returns only file name.
`debug`    | bool   | `false`       | Enable debug data for `debug()` method.
`expires`  | int    | `2592000`     | Cachefile expiring time (s).
`cache`    | bool   | `true`        | Cache enabled. Set false to force cachefile re-creation.
`compress` | bool   | `true`        | Compression enabled. Set false to compile files without compression (as is).
`newlines` | bool   | `true`        | Set true to make string one line. May break js code without line ending semicolon!
`dateform` | string | `d.m.Y H:i:s` | Date format for debug data.
`group`    | string | `main`        | Group name.
`prefix`   | string | `bmin`        | Prefix for compiled files.
`version`  | string |               | Version name or empty for no version.
`root`     | string |               | Server document root. Leave empty for auto generation.
`path`     | string |               | Server script path. Leave empty for auto generation.
`styles`   | string | `/css`        | Root folder for compiled css files (relative to path).
`scripts`  | string | `/js`         | Root folder for compiled js files (relative to path).

## Usage

Check out [examples at demo folder](./demo/) for usage instructions.

## License

[The MIT License (MIT)](./LICENCE.md)

Copyright (c) 2014 Blowback - https://github.com/BlowbackDesign
