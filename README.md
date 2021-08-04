wordpress-pgsql
===============
[![docker hub plugin](https://img.shields.io/badge/docker%20hub-1.0-00FF00)](https://hub.docker.com/r/smokserwis/wordpress-pgsql)
[![source at github](https://img.shields.io/badge/github-available-green)](https://github.com/piotrmaslanka/wordpress-pgsql)

A Wordpress Docker image with PostgreSQL support

# Usage

You need to provide following environment variables:

* `DB_NAME` - name of the PostgreSQL database to use
* `DB_USER` - login for PostgreSQL
* `DB_PASSWORD` - password for PostgreSQL
* `DB_HOST` - host where PostgreSQL is located

The container uses port 80 to handle WordPress
requests over HTTP.

As usual, after you configure your reverse proxy visit
https://domainname.example.com/wp-admin/install.php to install
WordPress.

To enable uploads to work, you should mount 
`/var/www/html/wp-content/uploads` as a volume.

## Reverse proxy terminating SSL

If you are using a reverse proxy to terminate SSL, make sure it 
passes the header of `X-Forwarded-Proto` as `https`, otherwise
WordPress will generate invalid links,
and also be sure to set the environment variable
of `FORCE_SSL` to `1`.

## Plugins surviving restarts

If you intend on installing custom plugins, you might want to
declare `/var/www/html/wp-content/plugins` as a volume.
Remember to place the necessary files there though.

# Special thanks

Special thanks to
[Shoaib Hassan](https://medium.com/@shoaibhassan_/install-wordpress-with-postgresql-using-apache-in-5-min-a26078d496fb)
without whom this script would have taken much longer to write!

Also thanks to [Kevin Locke](https://github.com/kevinoid/postgresql-for-wordpress)
for writing the code that enables WordPress to use PostgreSQL.

Also, many thanks to the wonderful community of 
[WordPress](https://wordpress.org/)!
