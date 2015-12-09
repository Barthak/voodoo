# voodoo

Voodoo is an opensource framework/engine/app collection, used mostly in combination with [World of Darkness Sheet Generator](https://github.com/Barthak/voodoo-sheetgen).

Please note that the guide below is a direct copy of the original installation guide and might contain errors or wrong references.

## How to install

This Guide assumes you have a fair share of knowledge of PHP, Apache and MySQL. 

### Step 1 - Download the latest release

Download the project to a temporary directory.

### Step 2 - Initial setup

In the temporary directory there are six subdirectories. In case you are setting the Engine up at a VirtualHost you can just copy all the six subdirectories to the location you desire and set the DocRoot in your VirtualHost directive to **/your/desired/location/docroot**. I recommend using this option.

In case you want the engine to run in a subdirectory of an already existing website/VirtualHost, I recommend to copy only the content of the **docroot** subdirectory to the desired location and placing all the other directories outside of your docroot for security purposes.

For example, your VirtualHost of yourwebsite.com is located at **/var/www/yourwebsite.com** and you want to place your Project Voodoo instance at yourwebsite.com/DoE, you could place the other five subdirectories in **/var/www/dalines.org.engine/**. If you go for such a setup there are two files that need modification.

First of all the file **voodoo.php** in the /docroot subdirectory needs to be edited. There is a line require_once('../conf/const.php');. Change that to link to the right directory where your const.php is located.

Secondly, the /conf/const.php file needs a few changes. All the defines in that file that are references to directory locations need to be modified correctly.

### Step 3 - Database creation

You need to create a MySQL database and a User with a Password that has SELECT,INSERT,UPDATE and DELETE privileges to that database. Once you have created the database you have to modify a few settings in /conf/engine.ini.

```
[database]
driver				= MySQL
server				= localhost
name				= ; Put your database name here
user				= ; Put the username here
password			= ; And the password should go here
```

**Note:** If your MySQL server is not on the webserver, please specify the hostname/ip in the server option.

### Step 4 - Project setup 

Once the above settings are updated you can connect to your Voodoo website. You will be redirected to **http://your/url/setup/Init**. This will show you all the SQL statements that you will need to execute to make the Voodoo pages work. I recommend copying the statements and manually inserting them into the database with a good tool or just through the Command Line mysql interface.

Alternatively, there is an option in the Engines AdminController that enables you to execute setup queries for Controllers through the web interface. I suggest not to use this, unless you are absolutely sure of what all is gonna be executed. This is especially true if you use Third Party Controllers. To enable this option, refer to your /spellbook/Admin/conf/admin.ini file and change the **insecure_sql_execution** to On.

After you have execute those queries in one way or another, your Project site is ready for usage. Please goto **http://your/url/setup/CreateAdmin**. You should now add an Admin user to your project. Once you have created an Admin user, you can edit your /conf/voodoo.ini file and change the site.setup from *Off* to *Complete*.

**Note:** You can only create an Admin user in case the project does not have one already, or, if your user has *admin.create* privileges.

Your Project site is now ready.