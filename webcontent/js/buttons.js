/**
 * Custom buttons handling functions
 */

$(document).ready(function() {
	// URL button
	$('.btnc-linkbutton').click(function() {
		let btn = $(this);
		let url = btn.data('url');
		let newtab = Boolean(btn.data('newtab'));

		if (! url) {
			window.alert('Software error: missing URL');
			return;
		}

		if (newtab)
			window.open(url, '_blank');
		else
			window.location.href=url;
	});

	// POST URL button
	$('.btnc-postbutton').click(function() {
		let btn = $(this);
		let url = btn.data('url');
		let newtab = Boolean(btn.data('newtab'));
		let post = btn.data('post');

		if (! url) {
			window.alert('Software error: missing URL');
			return;
		}

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
	});
});