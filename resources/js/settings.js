var Avatax = window.Avatax || {};

Avatax.settings = {

	init: function() {
		$('#settings-test-connection-btn').on('click', this.connectionTest);
	},

	connectionTest: function(e) {
		var message;
		var data = $('form[data-confirm-unload]').serialize();
		var self = Avatax.connectionTest;

		self.showLoading();

		Craft.postActionRequest('avataxTaxAdjuster/utilities/connectionTest', data, $.proxy(function(response, textStatus) {
            
            console.log(response);

            if (textStatus == 'success') {
                if (response.authenticated) {
                	message = 'Configuration validated successfully.';
                } else {
                    message = 'Could not connect with the current configuration.';
                }
            } else {
            	message = 'The request to avatax.com failed'
            }

            self.showModal(message);
            self.hideLoading();
        }, this));
	},

	showModal: function(message) {
		var modalHtml = 
			'<div class="modal fitted settings-modal-message">' +
				'<div class="header"><h1>Test Connection</h1></div>' +
				'<div class="body">'+message+'</div>' +
				'<div class="footer">' +
					'<div class="buttons right">' +
						'<input type="button" class="btn modal-cancel" value="'+Craft.t('Done')+'"/>' +
					'</div>' +
				'</div>' +
			'</div>';

		var $modal = $(modalHtml).appendTo(Garnish.$bod);
		$modal['modal'] = new Garnish.Modal($modal);

		$modal.find('.modal-cancel').on('click', function() {
			$modal['modal'].hide();
		});
	},

	showLoading: function() {
		$('#settings-test-connection-btn').addClass('disabled');
		$('#settings-test-connection-spinner').removeClass('hidden');
	},

	hideLoading: function() {
		$('#settings-test-connection-btn').removeClass('disabled');
        $('#settings-test-connection-spinner').addClass('hidden');
	}
};

$(function() {

	Avatax.settings.init();

});