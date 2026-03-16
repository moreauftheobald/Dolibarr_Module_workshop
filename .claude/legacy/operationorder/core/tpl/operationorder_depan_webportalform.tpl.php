<div class="box-flex-container field_date_operation_order">
	<div class="width20p"><?php echo $langs->trans('DateOperationOrder') ?></div>
	<div class="width80p">
		<div class="box-flex-container">
			<div class="width10p">
				<?php echo $this->form->inputDate('date_operation_order', $valueDateOperationOrder, '', 'date_operation_order', '', '') ?>
			</div>
			<div class="width10p">
				<?php echo $this->form->inputType('time', 'date_operation_order_time', $valueTimeOperationOrder, '', 'date_operation_order', '', '') ?>
			</div>
		</div>
	</div>
</div>
<div class="box-flex-container field_date_operation_order">
	<div class="width20p"><?php echo $langs->trans('PlannedDate') ?></div>
	<div class="width80p">
		<div class="box-flex-container">
			<div class="width10p">
				<?php echo $this->form->inputDate('planned_date', $valueDatePlanned, '', 'planned_date', '', '') ?>
			</div>
			<div class="width10p">
				<?php echo $this->form->inputType('time', 'planned_date_time', $valueTimePlanned, '', 'planned_date', '', '') ?>
			</div>
		</div>
	</div>
</div>
<br>
<div class="box-flex-container field_km_on_creation">
	<div class="width20p"><?php echo $langs->trans('km_on_creation') ?></div>
	<div class="width80p">
		<input type="number" id="km_on_creation" name="km_on_creation" value="<?php echo $km_on_creation ?>"/>
	</div>
</div>
<br>
<div class="box-flex-container field_supplier">
	<div class="width20p"><?php echo $langs->trans('Driver') ?></div>
	<div class="width80p">
		<?php echo $this->form->selectarray('fk_conducteur', $driverArray, $fk_conducteur, 1, 0, '', 0, '100%') ?>
		<?php echo ajax_combobox('fk_conducteur', array(), 0, 0, 'resolve', '-1') ?>
	</div>
</div>
<br>
<div class="box-flex-container field_supplier">
	<div class="width20p"><?php echo $langs->trans('Vehicule') ?></div>
	<div class="width80p">
		<?php echo $this->form->selectarray('fk_vehicule', $vehiculesArray, $fk_vehicule, 1, 0, '', 0, '100%') ?>
		<?php echo ajax_combobox('fk_vehicule', array(), 0, 0, 'resolve', '-1') ?>
	</div>
</div>
<br>
<div class="box-flex-container field_supplier">
	<div class="width20p"><?php echo $langs->trans('Atelier') ?></div>
	<div class="width80p">
		<?php echo $this->form->selectarray('fk_supplier', $supplierArray, $fk_supplier, 1, 0, '', 0, '100%') ?>
		<?php echo ajax_combobox('fk_supplier', array(), 0, 0, 'resolve', '-1') ?>
	</div>
</div>
<br>
<div class="box-flex-container  field_operations">
	<div class="width20p"><?php echo $langs->trans('Operations') ?></div>
	<div class="width80p">
		<?php echo $this->form->multiselectarray('operations', $productArray, $operations, 0, 0, "", 0, '100%') ?>
	</div>
</div>
<br>
<div class="box-flex-container field_subprice">
	<div class="width20p"><?php echo $langs->trans('UnitPrice') ?></div>
	<div class="width80p">
		<input type="number" step="0.01" id="subprice" name="subprice" value="<?php echo floatval($subprice) ?>"/>
	</div>
</div>
<br>
<div class="box-flex-container field_desc">
	<div class="width20p required"><?php echo $langs->trans('Description') ?></div>
	<div class="width80p">
		<textarea
			id="desc"
			style="resize:none"
			name="desc"
			wrap="soft"
			rows="5"
			class="centpercent"
			autofocus><?php echo dol_escape_htmltag($desc) ?></textarea>	
	</div>
</div>