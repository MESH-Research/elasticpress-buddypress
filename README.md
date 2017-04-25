# ElasticPress BuddyPress

This plugin provides a custom feature for [ElasticPress](https://github.com/10up/ElasticPress) which adds index & query support for BuddyPress groups & members.

Built for [Humanities Commons](https://hcommons.org).

# Initial setup:

Install & activate these plugins:

    buddypress
    elasticpress
    elasticpress-buddypress
    debug-bar-elasticpress ( if you use debug-bar - recommended )

Index posts:

    wp --url=example.com elasticpress index --network-wide

where `example.com` is your main site/network.

Index buddypress content:

    wp --url=example.com elasticpress-buddypress index

There are no hooks yet to re-index buddypress content. Run it on a regular basis to keep the index up-to-date.
