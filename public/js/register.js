function validate(userName) {
	$.post(
		'/username/available',
		{
			username: userName
		},
		function(responseData) {
			$('.username-validation').remove();
			
			if (responseData.available) {
				$('label[for="form_username"]').append(
					'<span class="username-available username-validation">  ✔</span>'
				);
				
				return;
			}

			$('label[for="form_username"]').append(
				'<span class="username-unavailable username-validation"> ❌</span>'
			);
		}
	);
}

$('#form_username').on('keyup', function(){
	validate($(this).val());
});