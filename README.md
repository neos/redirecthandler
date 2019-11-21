# Flow redirect handler

The Neos.RedirectHandler package for Flow allows to create redirects that will be returned to the client.

It can be used to divert changed URLs to new targets without having to adjust the webserver configuration
for each redirect.

## Installation

To use the redirect package, you have to install this package
	
	composer require "neos/redirecthandler"
	
and additionally a storage package. A default one for storing redirects in the database can be installed using composer with 

	composer require "neos/redirecthandler-databasestorage"
	
### Using this package with Neos CMS

Check out the [adapter package for Neos](https://github.com/neos/redirecthandler-neosadapter).

## Configuration

**Note**: When using this to handle redirects for persistent resources, you must adjust the default
rewrite rules! By default, any miss for `_Resources/…` stops the request and returns a 404 from the
webserver directly:
  
  	# Make sure that not existing resources don't execute Flow
	RewriteRule ^_Resources/.* - [L]

For the redirect handler to even see the request, this has to be removed. Usually the performance impact
can be neglected, since Flow is only hit for resources that once existed and to which someone still holds
a link.

## Possible problems

- When trying to redirect URLs with umlauts (or other special chars), be aware you might need to
  enter them urlencoded. But to be able to enter `%C3%BC` in place of `ü` you will need to adjust the
  source path validation regex to allow `%`.
