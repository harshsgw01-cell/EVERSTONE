<div class="modal fade" id="newBillToModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="newBillToForm">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Bill To</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <input class="form-control" name="title" placeholder="Title" required>
                        </div>
                        <div class="col-md-12">
                            <textarea class="form-control" name="address" placeholder="Address" required></textarea>
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
        const billToSelect = document.querySelector("select[name='billTo_id']");
        const modal = document.getElementById("newBillToModal");

        billToSelect.addEventListener("change", function () {
            if (this.value === "add_new") {
                this.value = "";
                new bootstrap.Modal(modal).show();
            }
        });

        document.getElementById("newBillToForm").addEventListener("submit", function (e) {
            e.preventDefault();
            let formData = new FormData(this);

            fetch("save-billTo.php", {
                method: "POST",
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        let option = new Option(data.title, data.id, true, true);
                        billToSelect.add(option);
                        bootstrap.Modal.getInstance(modal).hide();
                        this.reset();
                    } else {
                        alert("Error saving Bill To");
                    }
                });
        });
    });
</script>