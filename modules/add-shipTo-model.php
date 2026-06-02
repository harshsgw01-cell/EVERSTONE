<div class="modal fade" id="newShipToModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="newShipToForm">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Ship To</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <input class="form-control" name="name" placeholder="Name" required>
                        </div>
                        <div class="col-md-6">
                            <input class="form-control" name="title" placeholder="Title" required>
                        </div>
                        <div class="col-md-6">
                            <input class="form-control" name="company" placeholder="Company Name" required>
                        </div>
                        <div class="col-md-6">
                            <input class="form-control" type="email" name="email" placeholder="Email">
                        </div>
                        <div class="col-md-6">
                            <input class="form-control" name="phone" placeholder="Phone">
                        </div>
                        <div class="col-md-6">
                            <textarea class="form-control" name="address" placeholder="Address" rows="1"
                                required></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        const shipToSelect = document.querySelector("select[name='shipTo_id']");
        const modal = document.getElementById("newShipToModal");

        shipToSelect.addEventListener("change", function () {
            if (this.value === "add_new") {
                this.value = "";
                new bootstrap.Modal(modal).show();
            }
        });

        document.getElementById("newShipToForm").addEventListener("submit", function (e) {
            e.preventDefault();
            let formData = new FormData(this);

            fetch("save-shipTo.php", {
                method: "POST",
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        let option = new Option(data.name, data.id, true, true);
                        shipToSelect.add(option);
                        bootstrap.Modal.getInstance(modal).hide();
                        this.reset();
                    } else {
                        alert("Error saving Ship To");
                    }
                });
        });
    });
</script>