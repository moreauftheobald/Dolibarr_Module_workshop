<div class="box-flex-container field_firstname">
	<div class="width20p"><?php echo $langs->trans('DriverFirstname') ?></div>
	<div class="width80p">
		<input id="firstname" name="firstname" value="<?php echo $firstname ?>"/>
	</div>
</div>
<br>
<div class="box-flex-container field_lastname">
	<div class="width20p"><?php echo $langs->trans('DriverLastname') ?></div>
	<div class="width80p">
		<input id="lastname" name="lastname" value="<?php echo $lastname ?>"/>
	</div>
</div>
<br>
<div class="box-flex-container field_poste">
	<div class="width20p"><?php echo $langs->trans('DriverPoste') ?></div>
	<div class="width80p">
		<input id="poste" name="poste" value="<?php echo $poste ?>"/>
	</div>
</div>
<br>
<div class="box-flex-container field_civility">
	<div class="width20p"><?php echo $langs->trans('DriverCivility') ?></div>
	<div class="width80p">
		<?php echo $this->formcompany->select_civility($civility); ?>
	</div>
</div>
<br>
<div class="box-flex-container field_phone">
	<div class="width20p"><?php echo $langs->trans('DriverPhone') ?></div>
	<div class="width80p">
		<input id="phone" name="phone" value="<?php echo $phone ?>"/>
	</div>
</div>
<br>
<div class="box-flex-container field_email">
	<div class="width20p"><?php echo $langs->trans('DriverEmail') ?></div>
	<div class="width80p">
		<input id="email" name="email" value="<?php echo $email ?>"/>
	</div>
</div>