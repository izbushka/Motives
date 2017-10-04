// inserts Motives form into actiongroup form as it does not have correspondent event
function motives_extend_actiongroup_form() {
    var submitBtn = $('.widget-toolbox.padding-8.clearfix');
    var bonusForm = $('#motives_actiongroup_form');
    submitBtn.before(bonusForm);
}