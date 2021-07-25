wordpress-pgsql
===============
A Wordpress Docker image with PostgreSQL support


Usage:

You need to provide following environment variables:

* `DB_NAME` - name of the PostgreSQL database to use
* `DB_USER` - login for PostgreSQL
* `DB_PASSWORD` - password for PostgreSQL
* `DB_HOST` - host where PostgreSQL is located

The scripts uses port 80 to serve WordPress over HTTP, 
so configure your certificates on a reverse proxy.

If you are using a reverse proxy to terminate SSL, make sure it 
passes the header of `X-Forwarded-Proto` as `https`, otherwise
WordPress will generate invalid links.

As usual, after you configure your reverse proxy visit
https://domainname.example.com/wp-admin/install.php to install
WordPress.

# Advanced use cases

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
