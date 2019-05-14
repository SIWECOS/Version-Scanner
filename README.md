# SIWECOS CMS Version Scanner

This documentation describes the CMS Version scanner that has been developed as part of SIWECOS.

# Startup using Docker

`docker run -it --name siwecos-version-scanner -p 2015:80 -v /PATH/TO/SIGNATURES:/scanner/storage/signatures siwecos/version-scanner`

# Building a signature database

The scanner uses an extensive list of md5 hashsums to detect the used CMS and it's version. Building that database will consume considerable resources!
In order to build the database, execute the following command.

`docker run -it --rm -v /PATH/TO/SIGNATURES:/scanner/storage/signatures --entrypoint "/usr/local/bin/php" siwecos/version-scanner -d memory_limit=8092M /var/www/html/artisan svs:updatedatabase`

Please note that containers local storage path /scanner/storage/signatures is bound to the local directory. Use this directory in subsequent calls to let the container use this pre-generated database file.

## API-Call

Send a POST-Request to `http://localhost/api/v1/version`:

```
POST /api/v1/header HTTP/1.1
Host: localhost:8000
Content-Type: application/json
Cache-Control: no-cache


{
  "url": "https://siwecos.de",
  "callbackurls": [
    "http://localhost:9002"
  ]
}
```

The parameter `url` is required:

- `url` must be a `string`.

### Sample output

```json
{
  "name": "CMSVersion",
  "version": "1.0.0",
  "hasError": false,
  "errorMessage": null,
  "score": 100,
  "tests": [
    {
      "name": "CMSVERSION",
      "errorMessage": null,
      "hasError": false,
      "score": 100,
      "scoreType": "info",
      "testDetails": [
        {
          "translationStringId": "CMS_UPTODATE",
          "placeholders": {
            "cms": "Joomla",
            "version": "3.9.4"
          }
        }
      ]
    }
  ]
}
```
## CLI Command

Direct scanning via CLI is also possible, use this command for it:

`docker run -it --rm  -v /PATH/TO/SIGNATURES:/scanner/storage/signatures --entrypoint "/usr/local/bin/php" siwecos/version-scanner /var/www/html/artisan svs:version --website=https://example.com`

## HTTP-Output-Messages

| Placeholder         | Message                                                                                                             |
| ------------------- | ------------------------------------------------------------------------------------------------------------------- |
| CANT_DETECT_CMS     | CMS can't be detected.                                                                                              |
| CANT_DETECT_VERSION | The CMS has been detected, however the used version couldn't be determined                                          |
| CMS_OUT_OF_SUPPORT  | The used CMS version is not under vendor support anymore - it's therefore outdated and will not receive any updates |
| CMS_OUTDATED        | The used CMS version is not the latest available version if the branch, go update                                   |
| CMS_UPTODATE        | The CMS is up-to-date                                                                                               |
