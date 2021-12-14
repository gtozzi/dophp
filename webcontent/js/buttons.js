/**
 * Custom buttons handling functions
 */


/**
 * Sends user to an an URL via post, reads data stored in DOM
 */
function dophpPostButton(element) {
	let btn = $(element);
	let url = btn.data('url');
	let confirm = btn.data('confirm');
	let prompt = btn.data('prompt');
	let newtab = Boolean(btn.data('newtab'));
	let post = btn.data('post');

	if (! url) {
		window.alert('Software error: missing URL');
		return;
	}

	if( prompt )
		post.prompt = window.prompt(prompt);

	if( confirm && ! window.confirm(confirm) )
		return;

	let form = $('<form method="POST">');
	form.attr('action', url);
	for(let key in post) {
		let input = $('<input type="hidden">');
		input.attr('name', key);
		input.attr('value', post[key]);
		input.appendTo(form);
	}

	form.appendTo('body');
	form.submit();
}


$(document).ready(function() {
	// URL button
	$('.btnc-linkbutton').click(function() {
		let btn = $(this);
		let url = btn.data('url');
		let confirm = btn.data('confirm');
		let newtab = Boolean(btn.data('newtab'));

		if (! url) {
			window.alert('Software error: missing URL');
			return;
		}

		if( confirm && ! window.confirm(confirm) )
			return;

		if (newtab)
			window.open(url, '_blank');
		else
			window.location.href=url;
	});

	// POST URL button
	$('.btnc-postbutton').click(function() {
		let btn = $(this);
		dophpPostButton(btn);
	});
});
