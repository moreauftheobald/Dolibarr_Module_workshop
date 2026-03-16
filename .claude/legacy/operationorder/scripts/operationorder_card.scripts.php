<?php

?>
<!-- BEGIN SCRIPTS INCLUDING ALL CASE -->
<script>
	$('.inputhour').css('width', '100px');
	$('.inputminute').css('width', '100px');

	function orcheck() {
		$div = $('<div id="orcheck" style="overflow: hidden" title="<?php print $langs->trans('orcontrole'); ?>"><iframe width="100%" height="100%" frameborder="0" src="<?php print dol_buildpath('/operationorder/tpl/orcheck.php?orid=' . $object->id, 1); ?>"></iframe></div>');
		$div.dialog({
			modal: true
			, autoOpen: true
			, resizable: true
			, width: Math.max($(window).width() - 200, 1000)
			, height: Math.max($(window).height() - 100, 600)
			, close: function () {
				document.location.reload(true);
			}
		});
	};

	function supplierorderpiece() {
		let $div = $('<div id="divSupplierOrderPiece" style="overflow: hidden" title="<?php print $langs->trans('ProductsToOrder'); ?>"><iframe width="100%" height="100%" frameborder="0" src="<?php print dol_buildpath('/operationorder/tpl/ordersupplierfromor.php?orid=' . $object->id, 1); ?>"></iframe></div>');
		$div.dialog({
			modal: true
			, autoOpen: true
			, resizable: true
			, width: Math.max($(window).width() - 200, 1000)
			, height: Math.max($(window).height() - 100, 600)
			, close: function () {
				document.location.reload(true);
			}
		});
	};

	function debitpart() {
		$div = $('<div id="orcheck" style="overflow: hidden" title="<?php print $langs->trans('orcontrole'); ?>"><iframe width="100%" height="100%" frameborder="0" src="<?php print dol_buildpath('/operationorder/tpl/debitpart.php?orid=' . $object->id, 1); ?>"></iframe></div>');
		$div.dialog({
			modal: true
			, autoOpen: true
			, resizable: true
			, width: Math.max($(window).width() - 200, 1000)
			, height: Math.max($(window).height() - 100, 600)
			, close: function () {
				document.location.reload(true);
			}
		});
	};

	$(document).on('change', '#qty', function () {
		//Getting values
		let qty = $(this).val();
		let time_plannedhour = $(this).closest('form').find("#unitaire_timehour").val();
		let time_plannedmin = $(this).closest('form').find("#unitaire_timemin").val();
		let hoursToAdd = 0;
		//Parsing
		if (isNaN(qty)) qty = 0;
		else qty = parseFloat(qty);
		if (isNaN(time_plannedhour)) time_plannedhour = 0;
		else time_plannedhour = parseInt(time_plannedhour);
		if (isNaN(time_plannedmin)) time_plannedmin = 0;
		else time_plannedmin = parseInt(time_plannedmin);

		//Je convertis le tout en minutes
		time_plannedmin = ((time_plannedhour * 60) + time_plannedmin);

		let newTime_plannedmin = qty * time_plannedmin;
		if (newTime_plannedmin >= 60) {
			hoursToAdd = Math.floor(newTime_plannedmin / 60);
			newTime_plannedmin = newTime_plannedmin % 60;
		}

		$("#field_time_planned").find('input[name="time_plannedhour"]').val(hoursToAdd);
		$("#field_time_planned").find('input[name="time_plannedmin"]').val(Math.round(newTime_plannedmin));
	});

	$(document).ready(function () {

		$("#fk_product").on('change', function () {

			var fk_product = $(this).val();

			$.ajax({
				type: "POST",
				url: "<?php echo dol_buildpath('/operationorder/scripts/interface.php', 1) ?>",
				data: {
					action: 'getProductInfo',
					fk_operationOrder: <?php echo intval($object->id); ?>,
					fk_product: fk_product,
					token: '<?php echo currentToken()?>'
				},
				dataType: "json"
			}).done(function (data) {
				if (data.result > 0) {
					// Your code on success
					for (var i = 0; i < data.operationorders.length; i++) {
						let item = data.operationorders[i];
						$.jnotify(item.htmlAlert, "warning", true, {
							remove: function () {
							}
						});
					}

				} else {
					// Your code on fail
				}

				if (data.warningMsg.length > 0) {
					// display warnings from script
					$.jnotify(data.warningMsg, "warning", true, {
						remove: function () {
						}
					});
				}

				if (data.errorMsg.length > 0) {
					// display errors from script
					$.jnotify(data.errorMsg, "error", true, {
						remove: function () {
						}
					});
				}
			}).fail(function (response) {
				console.log("Error in ajax call");
				console.log(response);
				alert("Request failed: " + response);
			});

		});
	});

	function elementtoplan() {
		$div = $('<div id="elementtoplan"  title="<?php print $langs->trans('OperationOrderToCreate'); ?>"><iframe width="100%" height="100%" frameborder="0" src="<?php print dol_buildpath('/operationorder/tpl/ordertoplan.php', 1);?>?vhid=<?php print $object->fk_vehicule;?>&origin=order&orid=<?php print $object->id; ?>"></iframe></div>');
		$div.dialog({
			modal: true
			, width: "1200px"
			, height: $(window).height() - 200
			, close: function () {
				document.location.reload(true);
			}
		});
	};

	//function nonplanned() {
	//	$div = $('<div id="nonplanned" title="<?php //print $langs->trans('operationnonplanifiees'); ?>//"><iframe width="100%" height="100%" frameborder="0" src="<?php //print dol_buildpath('/operationorder/tpl/nonplanned.php?vhid=' . $object->fk_vehicule, 1) ?>//&origin=order&orid=<?php //print $object->id; ?>//"></iframe></div>');
	//	$div.dialog({
	//		modal: true
	//		, width: "1200px"
	//		, height: $(window).height() - 200
	//		, close: function () {
	//			document.location.reload(true);
	//		}
	//	});
	//}

</script>
<!-- END SCRIPTS INCLUDING ALL CASE -->
<?php

if ($action == 'create') {
	$fk_soc = GETPOST('fk_soc');
	$fk_vehicule = GETPOST('fk_vehicule');

	?>
	<!-- BEGIN SCRIPTS INCLUDING CREATE CASE -->
	<script>
		$(document).ready(function () {

			$("#fk_vehicule").change();

			$("#fk_vehicule").on('change', function () {
				let vehicule_id = $(this).val();
				if (vehicule_id !== '-1') {
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
						if (data.warningMsg.length > 0) {
							$.jnotify(data.warningMsg, "warning", true, {
								remove: function () {
								}
							});
						}
					}).fail(function (response) {
						console.log("Error in ajax call");
						console.log(response);
						alert("Request failed: " + response);
					});
				} else {
					$("#fk_soc").val('');
					$("#search_fk_soc").val('');
					$("#fk_soc").change();
				}
			});
		});

	</script>
	<!-- END SCRIPTS INCLUDING CREATE CASE -->
	<?php
}
if ($object->id > 0 && (empty($action) || ($action != 'edit' && $action != 'create'))) {
	?>
	<!-- BEGIN SCRIPTS INCLUDING NOT EDIT/CREATE CASE -->
	<script>
		//JS Fields
		$(document).ready(function () {
			<?php
			//panneau "warning" si action OR existe pour cet OR
			$ORA = new OperationOrderAction($db);
			$TORActions = $ORA->fetchByOR($object->id);
			if (!empty($TORActions)) {
				?>

			var element = $("td .fieldname_time_planned_f");
			$("td .fieldname_time_planned_f").append('<?php print img_picto($langs->trans('WarningORTimePlannedF'), 'warning.png') ?>');
			<?php } ?>
		});

		//nestedsorted line JSCRIPT.
		$(function () {
			// Animate modified line
			if (window.location.hash) {
				var hash = window.location.hash; //Puts hash in variable, and removes the # character
				// hash found
				//console.log($(hash).length);
				if ($(hash).length) {
					if ($(hash).hasClass("operation-order-sortable-list__item")) //operation-order-sortable-list__item__title
					{
						let itemTitleblock = $(hash).find("> .operation-order-sortable-list__item__title");

						$('html,body').animate({
							scrollTop: itemTitleblock.offset().top - 150
						}, 300);

						itemTitleblock.addClass("flipInX");
						itemTitleblock.addClass("animated");
					}
				}
			} else {
				// No hash found
			}


			var options = {
				insertZone: 5, // This property defines the distance from the left, which determines if item will be inserted outside(before/after) or inside of another item.
				placeholderClass: 'operation-order-sortable-list__item--placeholder',
				hintClass: 'operation-order-sortable-list__item--hint',
				onChange: function (cEl) {
					$("#ajaxResults").html("");

					$.ajax({
						url: "<?php echo dol_buildpath('operationorder/scripts/interface.php?action=setOperationOrderlevelHierarchy', 1) ?>",
						method: "POST",
						data: {
							'operation-order-id': ' <?php echo $object->id ?> ',
							'items': $('#sortableLists').sortableListsToHierarchy(),
							 'token': '<?php echo currentToken()?>'
						},
						dataType: "json",

						// La fonction à apeller si la requête aboutie
						success: function (data) {
							// Loading data
							if (data.result > 0) {
								// ok case
								var cardUrl = "<?php echo dol_buildpath('operationorder/operationorder_card.php', 2).'?id=' . $object->id ?>";
								var itemHash = "#item_"+cEl.data('id');
								console.log(cardUrl + itemHash);
								window.location.href = cardUrl + itemHash;
								location.reload(true);
							} else if (data.result < 0) {
								// error case
								$("#ajaxResults").html('<span class="badge badge-danger">' + data.errorMsg + '</span>');
							} else {
								// nothing to do ?
							}
						},
						// La fonction à appeler si la requête n\'a pas abouti
						error: function (jqXHR, textStatus) {
							alert("Request failed: " + textStatus);
						}
					});
				},
				complete: function (cEl) {

				},
				isAllowed: function (cEl, hint, target) {
					if (cEl.data('is_job') == 1 && target.data('id') !== undefined) {
						hint.addClass("hint-desabled");
						hint.removeClass("hint-enabled");
						return false;
					} else if (target.data('id') == undefined) {
						hint.removeClass("hint-desabled");
						hint.addClass("hint-enabled");
						return true;
					} else if (target.data('is_job') == 1 && cEl.data('is_job') == 0) {
						hint.removeClass("hint-desabled");
						hint.addClass("hint-enabled");
						return true;
					} else {
						hint.addClass("hint-desabled");
						hint.removeClass("hint-enabled");
						return false;
					}
				},
				handle: ".handle",
				insertZonePlus: true,
			};


			$('#sortableLists').sortableLists(options);
		});

		// MISE A JOUR AJAX DES LIGNES
		$(function () {
			var cardUrl = "<?php echo 'operationorder_card.php?id=' . $object->id ?>";
			var itemHash = "#item_<?php echo $lineid ?>";

			var dialogBox = jQuery("#dialog-form-edit");
			var width = $(window).width();
			var height = $(window).height();
			if (width > 700) {
				width = 700;
			}
			if (height > 600) {
				height = 600;
			}
			dialogBox.dialog({
				autoOpen: false,
				resizable: true,
				width: width,
				modal: true,
				buttons: {
					"<?php echo $langs->transnoentitiesnoconv('Update')?>": function () {
						dialogBox.find("form").submit();
					}
				},
				close: function (event, ui) {
					window.location.replace(cardUrl + itemHash);
				}
			});

			function popOperationOrderEditLineFormDialog(id) {
				var item = $("#edit-item_ + id ");
				dialogBox.dialog({
					title: item.attr("title")
				});
				dialogBox.dialog("open");
			}

			popOperationOrderEditLineFormDialog("'<?php echo intval($lineid)  ?>'");

		});

	</script>
	<!-- END SCRIPTS INCLUDING NOT EDIT/CREATE CASE -->
	<?php
}

if ($action == 'edit_attribute') {
	$attribute = GETPOST('attribute', 'alpha');
	if ($attribute === 'fk_c_operationorder_type') {
		?>
		<script type="text/javascript">
			$(document).ready(function () {
				// update type contract list
				$.ajax({
					url: "<?php echo dol_buildpath('/operationorder/scripts/interface.php', 1) ?>"
					, data: {
						get: 'get-operationorder-info-from-vehicule'
						, vehicule_id: <?php echo intval($object->id); ?>,
							token: '<?php echo currentToken()?>'
					}
					, dataType: "json"
					// La fonction à appeler si la requête n'a pas abouti
					, error: function (jqXHR, textStatus) {
						alert("Request failed: " + textStatus);
					}
				}).done(function (calldata) {
					var target = $("#fk_c_operationorder_type");
					var lastTargetValue = target.val();
					/* Remove all options from the select list */
					target.empty();
					target.prop("disabled", true);
					if (Array.isArray(calldata.operationorder_type)) {
						var data = calldata.operationorder_type;
						// empty field
						let newOption = $('<option>', {
							value: -1,
							text: '&nbsp;'
						});
						target.append(newOption);
						/* Insert the new ones from the array above */
						for (var i = 0; i < data.length; i++) {
							let item = data[i];
							let newOption = $('<option>', {
								value: item.id,
								text: item.label
							});
							target.append(newOption);
						}
						target.val(lastTargetValue).trigger('change');
						if (data.length > 0) {
							target.prop("disabled", false);
						}
					}
				});
			});
		</script>
		<?php
	}
}


?>
