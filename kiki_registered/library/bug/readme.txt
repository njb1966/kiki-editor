                            bug
           The Build Your Own Bug-up Markup Language

Bug is a markup language parser, much like Markdown.
But more importantly, Bug is a BYOB markup language: Build-Your-Own-Bugup. 
You can change the markup symbols to anything you like, and the syntax 
parser will automagically convert them into html tags.

It comes with a default symbol-tag set that *I* think are easy to use and
remember. Bug's default language is written with visual mnemonics, so it's 
a bit easier to remember the syntax. And the syntax follows consistent rules.
In fact, it's so simple that all of the characters you use to format build 
a little flying bug:

         {`}
      ~-=#*>: 
         [']

Rules:
* Bugtags with two symbols like ** or ~~ will wrap only one line of text.
* Bugtags with three symbols like >>> or ``` will wrap either one line
  or multiple lines of text.

	**bolded text**			...makes text bold using <strong
	~~italicized text~~		...makes text italic using <em>
	--centered text--		...centers the text using <center>
	>>>blockquoted text>>>	...blockquotes the text using <blockquote>
	[www.site.com]{A link}	... inserts a named hyperlink using <a href>
	[name of page]			...	inserts a link to local page name_of_page using <a href>
	#[www.site.org/cat.png]{picture of a cat} ... inserts an image with an 
                                alt-description using <img src alt title>
	'''preformatted text'''	...preformats multi-line text using <pre>
	```multi-line code```	...several lines of code wrapped with <code>
	=======heading1=======	...largest heading wrapped with <h1>
	======heading2======	...medium-large heading wrapped with <h2>
	=====heading3=====		...medium heading wrapped with <h3>
	====heading4====		...small-med heading wrapped with <h4>
	===heading5===			...smaller heading wrapped with <h5>
	==heading6==			...smallest heading wrapped with <h6>

Don't like the bug-up characters I used? It takes about 10 seconds
to change them into something you like more. Just modify the
characters you find in tags.bug and it will just work.
Play around with the characters until you find something you like more!