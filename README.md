# Flow redirect handler
[![Latest Stable Version](https://poser.pugx.org/neos/redirecthandler/v/stable)](https://packagist.org/packages/neos/redirecthandler)
[![License](https://poser.pugx.org/neos/redirecthandler/license)](https://packagist.org/packages/neos/redirecthandler)
[![Travis Build Status](https://travis-ci.org/neos/redirecthandler.svg?branch=master)](https://travis-ci.org/neos/redirecthandler)

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

### Upgrading from 2.x

The hit counter has been disabled by default.

You can enable it again in your Settings:

    Neos:
      RedirectHandler:
        features:
          hitCounter: true
          
The default status codes for redirects has been changed from 307 to 301 as 
mostly permanent redirects are desired instead of temporary.

You can enable the old behavior in your Settings:

    Neos:
      RedirectHandler:
        statusCode:
          redirect: 307
          
## Configuration

**Note**: When using this to handle redirects for persistent resources, you must adjust the default
rewrite rules! By default, any miss for `_Resources/…` stops the request and returns a 404 from the
webserver directly:
  
  	# Make sure that not existing resources don't execute Flow
	RewriteRule ^_Resources/.* - [L]

For the redirect handler to even see the request, this has to be removed. Usually the performance impact
can be neglected, since Flow is only hit for resources that once existed and to which someone still holds
a link.

### What to do when redirects are not triggered but other controller actions

Override the routing order like this:

    Neos:
      Flow:
        http:
          chain:
            process:
              chain:
                redirect:
                  position: 'before routing'
                  
Be careful when using this configuration, as this will make the redirect 
component act first before any other route is resolved and could for 
example prevent a login or similar.
                   
## Possible problems

- When trying to redirect URLs with umlauts (or other special chars), be aware you might need to
  enter them urlencoded. But to be able to enter `%C3%BC` in place of `ü` you will need to adjust the
  source path validation regex to allow `%`.
