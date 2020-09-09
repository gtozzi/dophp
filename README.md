# DoPhp
A Minimal, Fast and Easy-to-Use PHP Framework.

## Quick Start
Clone this repository inside your project directory, e.g.: *lib/dophp*.
```bash
#cd into project's root:
mkdir lib
cd lib
git clone https://git.ag2.it/gtozzi/dophp.git
```
Copy the content of *dophp/skel* directory inside the project's root.
```bash
#cd into project's root:
cp -R lib/dophp/skel/. .
```
Make sure the *cac* directory is writable.
```bash
#cd into project's root:
chmod +w cac/
```
Now duplicate the file named *config.devel.php* (which can be found inside the project's root after step 2) and rename the new copy to *config.php*.
```bash
#cd into project's root:
cp config.devel.php config.php
```
This .php file contains the basic server configuration parameters like the DataBase connection info, test's and debug's bits and the relative *dophp* directory location which needs to match with the folder path (`'url' => 'lib/dophp',` if you followed the example in step 1).

Give your website the appropriate name and a locale by editing *base.php* file.

Visit *index.php*'s URL from any browser and you should hit the home controller situated in *inc* directory (*index.home.php*) which renders the front end page that has the same name in *tpl* directory, but with *.tpl* extension instead. HTML, CSS and JavaScript code in *.tpl* files can be written by following [Smarty](https://www.smarty.net/)'s syntax, which is part of the framework.