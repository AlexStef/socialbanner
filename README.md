Setup SocialBanner
==================

Foreword
--------

This App is an example of PHP Website (built with [Silex](https://github.com/silexphp/Silex)) to understand how you can use [Creads Partners API](http://www.creads-partners.com/) along with [its PHP library](https://github.com/creads/partners-api-php) to build a lightweight application that allows users to order design products in the fewest steps possible.

Prerequisites
-------------

To use [Partners API Php Lib](https://github.com/creads/partners-api-php), you need

* An Access to Partners API : [http://www.creads-partners.com/api](http://www.creads-partners.com/api)
* PHP >= 5.4

To use this application fully as it is concieved, you need
* [Composer](https://github.com/composer/composer)
* A Dabatase (SQLite)
* A [Facebook App](https://developers.facebook.com/apps/)
* A [PayPlug Account](https://www.payplug.com/)

We use *Facebook* to provide a simple way for users to signup and login, you could disable this feature and signup users anyway you want (via a simple form for instance).

We use *PayPlug* to complete the user's payment safely, and publish the resource in Partners API only when it's done. You could replace the Payment process with anything that suits you.

Build with Docker
-----------------

Build the docker

```bash
docker build -t creads/socialbanner .
```

Run the docker

```bash
docker run -d -p 80:80 --name socialbanner creads/socialbanner
```

> Running a container is a bit longer probably because of `composer install`

> If the port `80` is already occupied, you can change by another port or `-p 80` and docker set the random port number.

You can configure the following environment

* `APP_ENV` can take `prod` (by default) or `dev`
* `INSTALL` can take `yes` (by default) to launch `composer install` at run or `no`

You can launch the container to develop the socialbanner and see changing without re-run the container.

```bash
docker run -d -p 80:80 --name socialbanner -v $PWD:/usr/share/nginx/html -e INSTALL=no -e APP_ENV=dev creads/socialbanner
```

At local you can watch on `http://localhost/`

Gettin Started
--------------

Install the dependencies with [Composer](https://github.com/composer/composer).

```bash
composer install
```

Allow the server user to access and edit needed directories

```
sudo chmod +a "_www allow delete,write,append,file_inherit,directory_inherit" {app/cache, web/data}
sudo chmod +a "`whoami` allow delete,write,append,file_inherit,directory_inherit" {app/cache, web/data}
```

Create the database

```bash
app/console doctrine:database:create
app/console orm:schema-tool:create
```

To launch the server manually

```
php -S localhost:8080 -t web web/index_dev.php
```

In `config.yml` you can configure Partners, Facebook and PayPlug APIs with the needed credentials. Therefore we need:

* A Partners app and its credentials (Ask one [here](http://www.creads-partners.com/api) )
* A Facebook app and its credentials (Create one [here](https://developers.facebook.com/apps/) )
* A PayPlug account and its key (if payment is used) ([PayPlug Website](https://www.payplug.com/))

```yaml
facebook:
    key: # Your Facebook App key
    secret: # Your Facebook App secret

partners_api:
    base_uri: # Partners API url
    app_id: # Your Partners App Id
    app_secret: # Your Partners App Secret

payment:
    currency: EUR
    base_uri: # Your app root url
    payplug_key: # Your Payplug account key
```


### Configuration

You can use a yml file by environment (`app/config/config_prod.yml`,`app/config/config_dev.yml`) to set/override the base configuration on each environment.

For example, if I want to have a different Facebook App registered in Production environment, I will write a file:

`app/config/config_prod.yml`

```yaml
    facebook
        key: my_facebook_key_for_prod
        secret: my_facebook_secret_for_prod
```


Besides, you can also write the default products configuration (see [Create a project]() below) into the config file, and it will load them.

See the default `app/config/config.yml` to see how it is declared

```yaml
default_projects:
    # Facebook Cover, add and edit to provide default projects
    # Get the GIDs, available options, and price from Partners API
    -
        product:
            gid: '56421809693d5'
        options:
            due: 2
            mode: 'solo'
            skill: 'conception'
        price:
            amount: 140.00
            currency: 'EUR'
        organization:
            gid: '19c8d61ecbbd4'
        description: 'Image de couverture Facebook au format <b>828x315</b>px.<br>'
    -
        product:
    # ... Another default project
```

How it works
------------

### User authentication

Our App is using OAuth2 authentication to send and fetch Resources form Partners API (create a project, post a comment, choose a design, ...). We will need to **store users** in order to associate them with the projects they will launch on Partners. Each time a new order is created, we send a request to Partners API and store the created Resource (a Project) Id associated to the user.

This App uses [gigablah/silex-oauth](https://github.com/gigablah/silex-oauth) to allow login via Facebook, along with a custom User Provider (`/src/Creads/SocialBanner/Common/Facebook/FacebookUserProvider.php`) which provides users to the firewall (defined in `app/app.php`);

When fetching a new user, FacebookUserProvider stores its Facebook Id in the database.

```php
<?php
$user = new User();
$user->setFacebookId($token->getUid());

$user->setEmail($token->getEmail());
$user->setFullName($token->getUsername());
$this->em->persist($user);
$this->em->flush();
```

> You can replace this logic with a classic login (*username/password*) form  and a standard UserProvider. See [Silex Documentation about Security](http://silex.sensiolabs.org/doc/providers/security.html) to read more about this.

### Partners API communication

*Partners API PHP Lib* allows you to request the API with http request without handling JSON coding/decoding and the authentication of each request.

> Everything described below is used in `/src/Creads/SocialBanner/App/Controller/DefaultControllerProvider.php`. Feel free to explore this ControllerProvider and test some things.

### Partners OAuth2 authentication

Partners API uses [OAuth2](https://www.digitalocean.com/community/tutorials/an-introduction-to-oauth-2) to handle request, which means each request from a client needs to be authenticated to succeed. This works through the passing of token in HTTP headers.

`/src/Creads/SocialBanner/Common/Partners/PartnersApiProvider.php` provides an easy access to the library. Besides, it handles OAuth2 authentication (provided correct client credentials).

The method `->getToken()` is used to ask the API for an access token for our App. It gives the token to the Library that will take care of keeping this token on each future request.

In `app/app.php` we imply register this Provider:

```php
<?php
$app->register(new Creads\SocialBanner\Common\Partners\PartnersApiProvider());
```

Then in **any Service** or **controller** we can request Partners API simply like this:

```php
<?php
$users = $app['partners']->get('users');
foreach ($users['items'] as $user) {
    echo $user['email'];
}
```

#### Create a project

Ordering on Partners means creating a **Project** resource. (Read more in [Partners API doc](http://www.creads-partners.com/api))

If you want to order a Logo, simply POST a new Project resource:

```php
<?php
$app['partners']->post('projects', array(
    'title' => 'Title of my logo order',
    'product' => ['gid' => '1234567891011'], // id of the Logo product you can get from API
    'options' => [
        'due' => 2, // project delay in days : 1,2,5 or 10,
          // See documentation for other required options
    ],
    'price' => [
        'amount' => 140.00,
        'currency' => 'EUR',
    ],
    'organization' => ['gid' => '1234567891011'], // id of your organization you can get from API
    'description' => 'This is what designers will see in the brief',
));

```

> The `app/config/config.yml` allows you to declare an array of default projects to be used via the config `partners_api.default_projects`

In the response header `Location`, you will get the created project's Id. We want to store this Id in our database to be able to fetch it later.

```php
<?php
// Extract the Partners GID from the Location header
$gid = str_replace('/v1/projects/', '', $res->getHeader('Location')[0]);

// Store the new project linked to our user
$project = new Project();
$project->setUser($owner);
$project->setPartnersGid($gid);

$app['orm.em']->persist($project);
$app['orm.em']->flush();
```

#### Publish a project

By default, a project is posted as a **Draft**. (Read more in [Partners API doc](http://www.creads-partners.com/api))

If you want to publish your order, set the state to `published`;

Let `1234567891011` be the project GID, you'll send:

```php
<?php
$app['partners']->put('projects/1234567891011', array(
    'state' => 'published'
));

```

#### Comments

**Comments** are resources linked to a **Project** via its `uri`. (Read more in [Partners API doc](http://www.creads-partners.com/api))

To comment a project, just POST a comment with the field `uri` being the project's `href`.

> Every Partners API Resource has a `href` field, which corresponds to the path in the API to get the resource

```php
<?php
$body = [
    'message' => 'I am commenting a project',
    'uri' => $partnersProject['href'],
];

// Send new comment request to the Partners API
$res = $app['partners']->post('comments', $body);
```

To retrieve a project's comments, simply search comments that reference this project

```php
<?php
// Get the comments
$query = ['uri', '==', $partnersProject['href']];
$commentsResponse = $app['partners']->get('comments?orderBy=created_at&sort=asc&query='.json_encode($query));
$comments = $commentsResponse['items'];
```

The `$query` variable here is formatted in Partners Query Language (**PQL**) and allows you to filter responses on a field value (with comparison operators). You can create complex queries with logical operators, refer to Partners API documentation to read more about this.

In the request, `?orderBy=created_at&sort=asc` allows us to order the response on a specific field and sorted.


#### Creations

The creations submitted by designers will appear as **Work** Resources, referencing the project. You can fetch them easily:

```php
<?php
// Get the works
$workQuery = ['project.gid', '==', $partnersProject['gid']];
$worksResponse = $app['partners']->get('works?query='.json_encode($workQuery));
$works = $worksResponse['items'];
```

#### End a project

A project ends when one of its **Work** has been selected as the winner of the project.

To select a winner, simply use a `PUT` on the project:

```php
<?php
$body = [
    'winner' => [
        'gid' => $partnersWork['gid'],
    ],
];

// Send project modification request to the Partners API
$res = $app['partners']->put('projects/'.$projectGid, $body);
```

### Payment

This App uses PayPlug to ask the User to complete a payment before publishing the order.

Basically, you simply need to have a PayPlug account and write your credentials in the configuration file for this to work.

What the App does is requesting PlayPlug API to create a **Payment** resource, then redirects the user to the returned link.

We gave PayPlug the callback url in the request, so we can listen to the successfull or failed payment.

Our callback URL is dealt by a controller which **publishes** the Partners Project if the payment is a success.

That's about it ! You can learn more by exploring `/src/Creads/SocialBanner/App/Controller/PaymentControllerProvider.php` and reading PayPlug API Reference.

[PayPlug Website](https://www.payplug.com/)


Copyright
---------

Copyright (c) 2016, Creads

All rights reserved.
