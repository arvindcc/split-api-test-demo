1. Clone GitHub repo for this project locally

git clone linktogithubrepo.com/ projectName

2. cd into your project

3. [Optional]: Checkout the “Start” tag so you have a fresh install of the project (and not the final files)
Keep in mind that this step is optional because not all git repos will have a start tag, but most the tutorials that I create for you will have a start tag. Otherwise you can skip this step.
git checkout tags/start -b tutorial

4. Install Composer Dependencies

composer install

5. Create a copy of your .env file

cp .env.example .env

6. Create an empty database for our application

7. In the .env file, add database information to allow Laravel to connect to the database
In the .env file fill in the DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, and DB_PASSWORD options to match the credentials of the database you just created. This will allow us to run migrations and seed the database in the next step.

8. Migrate the database
Once your credentials are in the .env file, now you can migrate your database.

php artisan migrate

It’s not a bad idea to check your database to make sure everything migrated the way you expected.
