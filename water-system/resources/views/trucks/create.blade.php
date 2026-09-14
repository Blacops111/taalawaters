<h2>Add New Truck</h2>

<form action="{{ route('trucks.store') }}" method="POST">

@csrf

<label>Truck Name:</label>
<input type="text" name="name" required>

<br><br>

<label>Plate Number:</label>
<input type="text" name="plate_number" required>

<br><br>

<label>Truck Type:</label>

<select name="type">

<option value="bottled">
Bottled Delivery Truck
</option>

<option value="tanker">
Water Tanker
</option>

</select>

<br><br>

<label>Capacity:</label>

<input type="number"
       name="capacity"
       placeholder="Cartons or Litres">

<br><br>

<button type="submit">

Add Truck

</button>

</form>
