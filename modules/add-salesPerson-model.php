<div class="modal fade" id="newSalesPersonModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="newSalesPersonForm">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Sales Person</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><input class="form-control" name="name" placeholder="Name" required></div>
                        <div class="col-md-6"><input class="form-control" name="title" placeholder="Title" required>
                        </div>
                        <div class="col-md-6"><input class="form-control" type="email" name="email" placeholder="Email"
                                required></div>
                        <div class="col-md-6"><input class="form-control" name="phone" placeholder="Phone" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Signature</label>
                            <input type="file" class="form-control" name="signature" accept="image/*">
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
        const salesPersonSelect = document.querySelector("select[name='salesPerson_id']");
        const modal = document.getElementById("newSalesPersonModal");

        salesPersonSelect.addEventListener("change", function () {
            if (this.value === "add_new") {
                this.value = "";
                new bootstrap.Modal(modal).show();
            }
        });

        document.getElementById("newSalesPersonForm").addEventListener("submit", function (e) {
            e.preventDefault();
            let formData = new FormData(this);

            fetch("save-salesPerson.php", {
                method: "POST",
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        let option = new Option(data.name, data.id, true, true);
                        salesPersonSelect.add(option);
                        bootstrap.Modal.getInstance(modal).hide();
                        this.reset();
                    } else {
                        alert("Error saving Sales Person");
                    }
                });
        });
    });
</script>