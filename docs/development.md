# Developing on Contao Manager

The *Contao Manager* development environment supports [hot-reloading]
of Vue components in the frontend. To develop the application, three
servers are required.


## PHP Backend API

The backend is a RESTful API written in PHP using the Symfony framework.
PHP offers an internal web server for development purposes, which we
can use to run the backend API.

```
$ php -S 127.0.0.1:8000 --docroot=public/
```

By default, the API places all Contao files in a `test-dir` folder inside
your GIT project root. You can override the location of Contao by setting
the `COMPOSER` environment variable according to the [Composer documentation].

> [!IMPORTANT]
> Be aware that the server must run on port 8000 for the dev frontend to
work correctly.


## Packages Index

The development build of the Contao Manager retrieves packages from a local
source instead of https://extensions.contao.org. You must therefore run a PHP server
of the [`contao/package-list`][package-list] repository.

On the first run, install the application by
1. cloning the repository to a separate folder
2. running `composer install` and `npm install` to install the dependencies
2. runing `npm run build` to generate the application files
3. creating an initial index of packages by running `api/console package-index`

Now you can start a local webserver using the following command

```
$ php -S 127.0.0.1:8001 --docroot=public/
```

> [!TIP]
> To update the local package index, run the `api/console package-index` command.


## Javascript Frontend UI

The frontend is a SPA (Single Page Application) build using Vue.js.
To start the frontend server run the following command:

```
$ npm run serve
```

This will automatically open your default browser with the frontend.


[hot-reloading]: https://vue-loader.vuejs.org/en/features/hot-reload.html
[Composer documentation]: https://getcomposer.org/doc/03-cli.md#composer
[package-list]: https://github.com/contao/package-list
