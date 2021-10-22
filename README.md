# DoPhp
A Minimal, Fast and Easy-to-Use PHP Framework.

## Quick Start with Docker
Create your project's root directory, then add a source folder e.g.: *web* (which is going to be the one containing the *index.html* file)
Then clone this repository inside your project source directory, e.g.: *web/lib/dophp* (you may need to add it as submodule too).
```bash
mkdir app && cd app
git init
mkdir web && cd web
mkdir lib && cd lib
git clone https://git.ag2.it/gtozzi/dophp.git
cd ../..
git submodule add https://git.ag2.it/gtozzi/dophp.git web/lib/dophp/
```
Copy the content of skel directory inside the source directory to get the basic code.
```bash
cp -R web/lib/dophp/skel/. .
```
Make sure the *cac* directory is writable.
```bash
chmod +w -R web/cac/
```
Now duplicate the file named *config.devel.php* (which can be found inside the project's source after step 2) and rename the new copy to *config.php*.
```bash
cp web/config.devel.php web/config.php
```
*config.php* file contains the basic server configuration parameters like the DataBase connection info, test's and debug's bits and the relative *dophp* directory location which needs to match with the folder path (`'url' => 'lib/dophp',` if you followed the example in step 1).

Give your website the appropriate name and a locale by editing *base.php* file.

Start containers' orchestration
```bash
docker-compose up -d
```
Visit *localhost:8081* (if you didn't change *docker-compose.yml*) from any browser and you should hit the home controller situated in *inc* directory (*index.home.php*) which renders the front end page that has the same name in *tpl* directory, but with *.tpl* extension instead. HTML, CSS and JavaScript code in *.tpl* files can be written by following [Smarty](https://www.smarty.net/)'s syntax, which is part of the framework.