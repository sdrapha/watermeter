## This fork's added info

Docker compose for this fork:
```Yaml
services:
  watermeter:
    image: riulopkat/watermeter:develop
    container_name: watermeter
    volumes:
      - ./watermeter/config:/usr/src/watermeter/src/config
    restart: unless-stopped
    ports:
      - "3000:3000"
```
Then access: 

    http://watermeter:3000/configure.php

Tip: The service will only output an updated value if the OCR recognized new value is greater than the `last_value` but also within the range of `max_threshold`, otherwise will be discarded as invalid 

For the `/config` docker compose mapped folder, when mapped, if mounted to the container empty, the service will fail to start. So you must provide the files ahead of time `config.php`. You can copy it from the github repo `src/config/` for the initial setup.

_____________

**Original repo info ►**
# Read a water meter and returns value

Reads analog water meters and provides a web service that returns the read value as decimal. The needles of the analog gauges currently have to be red.

Turns ![Watermeter](doc/watermeter.jpg) into ```820.5745``` so it can become ![Grafana Screenshot](doc/grafana.png).

[![CI](https://github.com/nohn/watermeter/workflows/CI/badge.svg)](https://github.com/nohn/watermeter/actions/workflows/ci.yml?query=branch%3Amain) [![Docker Hub Pulls](https://img.shields.io/docker/pulls/nohn/watermeter?label=docker%20hub%20pulls)](https://hub.docker.com/r/nohn/watermeter/tags?page=1&ordering=last_updated)

## Getting Started

This is only a quick introduction to setting up and configuring watermeter. A more extensive documentation can be found in [the howto](doc/HOWTO.md).

### Installation

#### Using Docker Compose (recommended)

```yaml
version: "3.5"
services:
  watermeter:
    image: nohn/watermeter:latest
    container_name: watermeter
    volumes:
      - ./watermeter/config:/usr/src/watermeter/src/config
    restart: always
    ports:
      - "3000:3000"
```

### Configuration

You can access the configuration tool http://watermeter:3000/configure.php. The interface should be self explanatory. Source Image can be either in local filesystem or any HTTP(S) resource.

![Configuration GUI Screenshot](doc/configure.png)

After configuration is done, you can access the current value at

    http://watermeter:3000/

or

    http://watermeter:3000/?json

or see debug information at

    http://watermeter:3000/?debug

## How to contribute

You can contribute to this project by:

* Opening an [Issue](https://github.com/nohn/watermeter/issues) if you found a bug or wish to propose a new feature
* Placing a [Pull Request](https://github.com/nohn/watermeter/pulls) with bugfixes, new features etc.

## You like this?

Consider a [gift](https://www.amazon.de/hz/wishlist/ls/3HYH6NR8ZI0WI?ref_=wl_share).

## License

analogmeterreader is released under the [GNU Affero General Public License](LICENSE).
