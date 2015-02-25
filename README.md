Redirect by post ID in URL
==========================

*WordPress Plugin*

**This plugin facilitates slug changes by using the `%post_id%` in the URL
and even slight slug changes.**

If no `%post_id%` is used than current slug words are used to redirect posts to
best possible match. This facilitates the permalink struct changes.

Redirects work with most crazy scenarios. Lets say `ID` is `123`, the final URL
is `http://www.example.com/posts/2014/12-some-title-123/feed`. I added `feed`
just to show that even endpoints would work.

These URLs should redirect to final URL:

- http://www.example.com/posts/2014/12-some-123/feed
- http://www.example.com/posts/2014/some-123/feed
- http://www.example.com/2014/some-123/feed
- http://www.example.com/12-some-123/feed
- http://www.example.com/posts/123/feed
- http://www.example.com/123/feed

The shortest URL can be used as custom short URL.

Enjoy!

---

[@martin_adamko](http://twitter.com/martin_adamko)
