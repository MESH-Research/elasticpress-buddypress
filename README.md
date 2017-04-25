Initial setup:

Install & activate these plugins:

    elasticpress
    elasticpress-buddypress
    debug-bar-elasticpress ( if you use debug-bar - recommended )

Index posts:

    wp --url=example.com elasticpress index --network-wide

where `example.com` is your main site/network.

Index buddypress content:

    wp --url=example.com elasticpress-buddypress index

There are no hooks yet to re-index buddypress content. Run it on a regular basis to keep the index up-to-date.
