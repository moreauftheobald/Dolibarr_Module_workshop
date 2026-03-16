<?php
$fk_soc = GETPOST('fk_soc');
$fk_vehicule = GETPOST('fk_vehicule');
?>
<!-- BEGIN SCRIPTS INCLUDING ALL CASE -->
<script>
	$('.inputhour').css('width', '100px');
	$('.inputminute').css('width', '100px');

	$(document).ready(function () {

		$("#fk_product").on('change', function () {

			var fk_product = $(this).val();

			$.ajax({
				type: "POST",
				url: "<?php echo dol_buildpath('/operationorder/scripts/interface.php', 1) ?>",
				data: {
					action: 'getProductInfos'
					, fk_product: fk_product
					, token: '<?php echo currentToken()?>'
				},
				dataType: "json"
			}).done(function (data) {
				if (parseFloat(data.price) > 0) {
					$('#subprice').val(data.price);
				}

				if (data.errorMsg.length > 0) {
					// display errors from script
					$.jnotify(data.errorMsg, "error", true, {
						remove: function () {
						}
					});
				}
			});

		});

		$("#fk_vehicule").on('change', function () {

			var vehicule_id = $(this).val();

			// Update fk_soc

			$.ajax({
				type: "POST",
				url: "<?php echo dol_buildpath('/operationorder/scripts/interface.php', 1) ?>",
				data: {
					action: 'getSocOfVehicule',
					vehicule_id: vehicule_id,
					token: '<?php echo currentToken()?>'
				},
				dataType: "json"
			}).done(function (data) {
				if (data.result > 0) {
					$("#fk_soc").val(data.societe.id);
					$("#search_fk_soc").val(data.societe.name);
					$("#fk_soc").change();
				} else {
					$("#fk_soc").val("");
					$("#search_fk_soc").val("");
					$("#fk_soc").change();
				}


				if (data.errorMsg.length > 0) {
					$.jnotify(data.errorMsg, "error", true, {
						remove: function () {
						}
					});
				}
			});
		});
	});

</script>
<!-- END SCRIPTS INCLUDING ALL CASE -->
