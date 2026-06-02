<!-- ══ ADD NEW PRODUCT MODAL ══ -->
<div class="modal fade" id="newProductModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius:16px;border:none;">
            <form id="newProductForm">

                <!-- Header -->
                <div style="background:linear-gradient(135deg,#0f172a,#1e293b);border-radius:16px 16px 0 0;padding:18px 22px;">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <div style="width:38px;height:38px;border-radius:10px;background:rgba(217,119,6,.25);border:1px solid rgba(253,211,77,.3);display:flex;align-items:center;justify-content:center;color:#fcd34d;font-size:1rem;flex-shrink:0;">
                                <i class="bi bi-box-seam-fill"></i>
                            </div>
                            <div>
                                <div style="font-size:.95rem;font-weight:700;color:#fff;">Add New Product</div>
                                <div style="font-size:.72rem;color:rgba(255,255,255,.4);">Fill in product details below</div>
                            </div>
                        </div>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                </div>

                <!-- Body -->
                <div class="modal-body px-4 py-3">
                    <div class="row g-3">
                        <div class="col-5">
                            <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;margin-bottom:5px;">
                                Product Code <span class="text-danger">*</span>
                            </div>
                            <input type="text" name="code" id="newProductCode"
                                class="form-control"
                                style="border:1.5px solid #e2e8f0;border-radius:9px;font-size:.84rem;height:40px;"
                                placeholder="e.g. P00001" required>
                        </div>
                        <div class="col-7">
                            <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;margin-bottom:5px;">
                                Product Name <span class="text-danger">*</span>
                            </div>
                            <input type="text" name="name"
                                class="form-control"
                                style="border:1.5px solid #e2e8f0;border-radius:9px;font-size:.84rem;height:40px;"
                                placeholder="e.g. Tactical Vest" required>
                        </div>
                        <div class="col-6">
                            <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;margin-bottom:5px;">
                                Part Number
                            </div>
                            <input type="text" name="part_number"
                                class="form-control"
                                style="border:1.5px solid #e2e8f0;border-radius:9px;font-size:.84rem;height:40px;"
                                placeholder="e.g. Everstone-4521">
                        </div>
                        <div class="col-6">
                            <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;margin-bottom:5px;">
                                Manufacturer
                            </div>
                            <input type="text" name="manufacturer"
                                class="form-control"
                                style="border:1.5px solid #e2e8f0;border-radius:9px;font-size:.84rem;height:40px;"
                                placeholder="e.g. Safariland">
                        </div>
                        <div class="col-12">
                            <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;margin-bottom:5px;">
                                Description
                            </div>
                            <textarea name="description" rows="2"
                                class="form-control"
                                style="border:1.5px solid #e2e8f0;border-radius:9px;font-size:.84rem;resize:vertical;"
                                placeholder="Short product description"></textarea>
                        </div>
                        <div class="col-6">
                            <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;margin-bottom:5px;">
                                Sales Price (€)
                            </div>
                            <input type="number" name="sales_price" step="0.01" min="0" value="0.00"
                                class="form-control"
                                style="border:1.5px solid #e2e8f0;border-radius:9px;font-size:.84rem;height:40px;">
                        </div>
                        <div class="col-6">
                            <div style="font-size:0.72rem;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;margin-bottom:5px;">
                                Cost Price (€)
                            </div>
                            <input type="number" name="cost_price" step="0.01" min="0" value="0.00"
                                class="form-control"
                                style="border:1.5px solid #e2e8f0;border-radius:9px;font-size:.84rem;height:40px;">
                        </div>
                        <div class="col-12">
            <div class="modal-label">Catalog File <span style="font-weight:400;text-transform:none;letter-spacing:0;color:#94a3b8;">(PDF, optional)</span></div>
            <input type="file" name="catalog_file" accept=".pdf,.doc,.docx"
                class="form-control modal-input" style="height:auto;padding:6px 10px;">
        </div>
                    </div>

                    <!-- Save result message -->
                    <div id="newProductMsg" style="display:none;margin-top:12px;"></div>
                </div>

                <!-- Footer -->
                <div class="modal-footer border-0 px-4 py-3" style="background:#f8fafc;border-radius:0 0 16px 16px;">
                    <button type="button" class="btn btn-outline-secondary btn-sm px-4" data-bs-dismiss="modal">
                        <i class="bi bi-x-lg me-1"></i>Cancel
                    </button>
                    <button type="submit" class="btn btn-sm px-4"
                        style="background:#d97706;color:#fff;border-color:#b45309;border-radius:10px;font-weight:600;">
                        <i class="bi bi-check2 me-1"></i>Save Product
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>