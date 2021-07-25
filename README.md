wordpress-pgsql
===============
A Wordpress Docker image with PostgreSQL support


Usage:

You need to provide following environment variables:

* DB_NAME - name of the PostgreSQL database to use
* DB_USER - login for PostgreSQL
* DB_PASSWORD - password for PostgreSQL
* DB_HOST - host where PostgreSQL is located

The scripts uses port 80 to serve WordPress over HTTP, 
so configure your certificates on a reverse proxy.

# Special thanks

Special thanks to
[Shoaib Hassan](https://medium.com/@shoaibhassan_/install-wordpress-with-postgresql-using-apache-in-5-min-a26078d496fb)
without whom this script would have taken much longer to write!

Also thanks to [Kevin Locke](https://github.com/kevinoid/postgresql-for-wordpress)
for writing the code that enables WordPress to use PostgreSQL.

Also, many thanks to the wonderful community of 
[WordPress](https://wordpress.org/)!
