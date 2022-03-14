$(function() {

    let ModalDeleteVisibilityGroup = $("#modalDeleteVisibilityGroup");
    let SubmitDeleteVisibilityGroup = $("#submitDeleteVisibilityGroup");
    let urlDeleteVisibilityGroup = Routing.generate('visibility_group_delete', true)
    InitModal(ModalDeleteVisibilityGroup, SubmitDeleteVisibilityGroup, urlDeleteVisibilityGroup, {tables: [visibilityGroupTable]});

});
