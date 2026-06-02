<div class="modal fade" id="newCustomerModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="newCustomerForm">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Customer</h5>
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
                        <div class="col-md-12"><textarea class="form-control" name="address" placeholder="Address"
                                required></textarea></div>
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
        const customerSelect = document.querySelector("select[name='customer_id']");
        const modal = document.getElementById("newCustomerModal");

        customerSelect.addEventListener("change", function () {
            if (this.value === "add_new") {
                this.value = "";
                new bootstrap.Modal(modal).show();
            }
        });

        document.getElementById("newCustomerForm").addEventListener("submit", function (e) {
            e.preventDefault();
            let formData = new FormData(this);

            fetch("save-customer.php", {
                method: "POST",
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        let option = new Option(data.name, data.id, true, true);
                        customerSelect.add(option);
                        bootstrap.Modal.getInstance(modal).hide();
                        this.reset();
                    } else {
                        alert("Error saving customer");
                    }
                });
        });
    });
</script>