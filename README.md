elasticsearch-bundle
====================

###This bundle only works with the networking/init-cms-bundle!

This bundle will provide search capabilities to your networking initcms
with default setup for pages and media (PDF files).


Install bundle via composer.

    "require": {
        ....
        "networking/elasticsearch-bundle": "^2.0",
        ...
    }
    "repositories": [
        {
            "type": "vcs",
            "url": "git@github.com:networking/elasticsearch-bundle.git"
        }
    ],

Update your AppKernel.php

```
<?php
	// app/AppKernel.php
	public function registerbundles()
	{
	    return array(
	        // ...
            new Networking\InitCmsBundle\NetworkingInitCmsBundle(),
            new Networking\ElasticSearchBundle\NetworkingElasticSearchBundle()
	    );
	}
```

Add the following parameters to your parameters.yaml

    elastic_search_host: localhost
    elastic_search_index: index_name #replace with the name of your search index
    
In your routing.yaml file, and the routing for the search action

    networking_elastic_search:
        resource: "@NetworkingElasticSearchBundle/Resources/config/routing.yaml"
        prefix:   /
    
And finally add the type configuration to the config.yaml for the minimum 
indexing of pages and media
    
```
fos_elastica:
        clients:
            default: { host: %elastic_search_host%, port: 9200 }
        indexes:
            %elastic_search_index%:
                client: default
                finder: ~
                settings:
                    index:
                        analysis:
                            analyzer:
                                my_search_analyzer:
                                    type: custom
                                    tokenizer: standard
                                    filter: [standard, lowercase, deu_snowball]
                                deu_snowball:
                                    type: snowball
                                    language: German2
                            filter:
                                 deu_snowball:
                                    type: snowball
                                    language: German2

                types:
                    page:
                        mappings:
                            name: {boost: 30, analyzer: my_search_analyzer}
                            metaTitle:  {"index" : "no"}
                            locale: { type: 'string', store: false}
                            content: {boost: 30, analyzer: my_search_analyzer}
                            url: {"index" : "no"}
                    media:
                        mappings:
                            name: {boost: 30, analyzer: my_search_analyzer}
                            metaTitle:  {"index" : "no"}
                            locale: { type: 'string', store: false}
                            content: {boost: 30, analyzer: my_search_analyzer}
                            url: {"index" : "no"}
                        persistence:
                            driver: orm # orm, mongodb, propel are available
                            model: Networking\InitCmsBundle\Entity\Media
                            provider:
                                service: networking_elastic_search.search_provider.media
                            listener:
                                insert: false
                                update: false
                                delete: true
```

In order to silence Elasticsearch Server errors in production mode add
the following parameter to the config_prod.yaml file. This extension of
the default Elastica Client will prevent a 500 error should the Elastic 
host no longer be available.

    parameters:
        fos_elastica.client.class: Networking\ElasticSearchBundle\Elastica\Client
        


