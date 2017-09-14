/* global jQuery:false, wpApiSettings:false */

jQuery.ajaxSetup({
  beforeSend: function(xhr) {
    xhr.setRequestHeader('X-WP-Nonce', wpApiSettings.nonce);
  },
})

jQuery(document).ready(function($) {
function save_field_api(groupId, post_data){
  return jQuery.ajax({
    type:"POST",
    data:JSON.stringify(post_data),
    contentType: "application/json; charset=utf-8",
    dataType: "json",
    url: wpApiSettings.root + 'dt-hooks/v1/group/'+ groupId,
  })
}

function get_group(groupId){
  return jQuery.ajax({
    type:"GET",
    contentType: "application/json; charset=utf-8",
    dataType: "json",
    url: wpApiSettings.root + 'dt-hooks/v1/group/'+ groupId
  })
}

function add_item_to_field(groupId, post_data) {
  return jQuery.ajax({
    type: "POST",
    data: JSON.stringify(post_data),
    contentType: "application/json; charset=utf-8",
    dataType: "json",
    url: wpApiSettings.root + 'dt-hooks/v1/group/' + groupId + '/details',
  })
}

function remove_item_from_field(groupId, fieldKey, valueId) {
  let data = {key: fieldKey, value: valueId}
  return jQuery.ajax({
    type: "DELETE",
    data: JSON.stringify(data),
    contentType: "application/json; charset=utf-8",
    dataType: "json",
    url: wpApiSettings.root + 'dt-hooks/v1/group/' + groupId + '/details',
  })
}

function post_comment(groupId, comment) {
  return jQuery.ajax({
    type: "POST",
    data: JSON.stringify({comment}),
    contentType: "application/json; charset=utf-8",
    dataType: "json",
    url: wpApiSettings.root + 'dt-hooks/v1/group/' + groupId + '/comment',
  })
}

function get_comment(groupId) {
  return jQuery.ajax({
    type: "GET",
    contentType: "application/json; charset=utf-8",
    dataType: "json",
    url: wpApiSettings.root + 'dt-hooks/v1/group/' + groupId + '/comments',
  })
}

function get_activity(groupId) {
  return jQuery.ajax({
    type: "GET",
    contentType: "application/json; charset=utf-8",
    dataType: "json",
    url: wpApiSettings.root + 'dt-hooks/v1/group/' + groupId + '/activity',
  })
}

$( document ).ajaxComplete(function(event, xhr, settings) {
  if (settings && settings.type && (settings.type === "POST" || settings.type === "DELETE")){
    refreshActivity()
  }
});

/**
 * Typeahead functions
 */

function add_typeahead_item(groupId, fieldId, val, name) {
  add_item_to_field(groupId, { [fieldId]: val }).done(function (addedItem){
    jQuery(`.${fieldId}-list`).append(`<li class="${addedItem.ID}">
    <a href="${addedItem.permalink}">${_.escape(addedItem.post_title)}</a>
    <button class="details-remove-button details-edit"
            data-field="locations" data-id="${val}"
            data-name="${name}"  
            style="display: inline-block">Remove</button>
    </li>`)
  })
}

function filterTypeahead(array, existing = []){
  return _.differenceBy(array, existing.map(l=>{
    return {ID:l.ID, name:l.display_name}
  }), "ID")
}

function defaultFilter(q, sync, async, local, existing) {
  if (q === '') {
    sync(filterTypeahead(local.all(), existing));
  }
  else {
    local.search(q, sync, async);
  }
}
let searchAnyPieceOfWord = function(d) {
  var tokens = [];
  //the available string is 'name' in your datum
  var stringSize = d.name.length;
  //multiple combinations for every available size
  //(eg. dog = d, o, g, do, og, dog)
  for (var size = 1; size <= stringSize; size++) {
    for (var i = 0; i + size <= stringSize; i++) {
      tokens.push(d.name.substr(i, size));
    }
  }
  return tokens;
}


  let group = {}
  let groupId = $('#group-id').text()
  let editingAll = false



/**
 * Group details Info
 */
  function toggleEditAll() {
    $(`.details-list`).toggle()
    $(`.details-edit`).toggle()
    editingAll = !editingAll
  }
  $('#edit-details').on('click', function () {
    toggleEditAll()
  })

  $(document)
    .on('click', '.details-remove-button', function () {
    let fieldId = $(this).data('field')
    let itemId = $(this).data('id')

    remove_item_from_field(groupId, fieldId, itemId).done(()=>{
      $(`.${fieldId}-list .${itemId}`).remove()

      //add the item back to the locations list
      if (fieldId === 'locations'){
        locations.add([{ID:itemId, name: $(this).data('name')}])
      }
      if (fieldId === "members"){
        members.add([{ID:itemId, name: $(this).data('name')}])
      }
    }).error(err=>{
      console.log(err)
    })
  })


  function toggleEdit(field){
    if (!editingAll){
      $(`.${field}.details-list`).toggle()
      $(`.${field}.details-edit`).toggle()
    }
  }


  /**
   * End Date
   */
  let endDateList = $('.end_date.details-list')
  let endDatePicker = $('.end_date #end-date-picker')
  endDatePicker.datepicker({
    onSelect: function (date) {
      console.log(date)
      save_field_api(groupId, {end_date:date}).done(function () {
        endDateList.text(date)
      })
    },
    onClose: function () {
      toggleEdit('end_date')
    },
    changeMonth: true,
    changeYear: true
  })
  endDateList.on('click', e=>{
    toggleEdit('end_date')
    endDatePicker.focus()
  })

  /**
   * Start date
   */
  let startDateList = $('.start_date.details-list')
  let startDatePicker = $('.start_date #start-date-picker')
  startDatePicker.datepicker({
    onSelect: function (date) {
      console.log(date)
      save_field_api(groupId, {start_date:date}).done(function () {
        startDateList.text(date)
      })
    },
    onClose: function () {
      toggleEdit('start_date')
    },
    changeMonth: true,
    changeYear: true
  })
  startDateList.on('click', e=>{
    toggleEdit('start_date')
    startDatePicker.focus()
  })

  /**
   * Assigned To
   */
  $('.assigned_to.details-list').on('click', e=>{
    console.log(e)
    toggleEdit('assigned_to')
    assigned_to_typeahead.focus()
  })
  var users = new Bloodhound({
    datumTokenizer: Bloodhound.tokenizers.obj.whitespace('display_name'),
    queryTokenizer: Bloodhound.tokenizers.ngram,
    identify: function (obj) {
      return obj.display_name
    },
    prefetch: {
      url: wpApiSettings.root + 'dt/v1/users/',
    },
    remote: {
      url: wpApiSettings.root + 'dt/v1/users/?s=%QUERY',
      wildcard: '%QUERY'
    }
  });

  function defaultusers(q, sync, async) {
    if (q === '') {
      sync(users.all());
    }
    else {
      users.search(q, sync, async);
    }
  }

  let assigned_to_typeahead = $('.assigned_to .typeahead')
  assigned_to_typeahead.typeahead({
    highlight: true,
    minLength: 0,
    autoselect: true,
  },
  {
    name: 'users',
    source: defaultusers,
    display: 'display_name'
  })
  .bind('typeahead:select', function (ev, sug) {
    console.log(sug)
    save_field_api(groupId, {assigned_to: 'user-' + sug.ID}).done(function () {
      assigned_to_typeahead.typeahead('val', '')
      jQuery('.current-assigned').text(sug.display_name)
    })
  }).bind('blur', ()=>{
    toggleEdit('assigned_to')
  })


  /**
   * Locations
   */
  let locations = new Bloodhound({
    datumTokenizer: searchAnyPieceOfWord,
    queryTokenizer: Bloodhound.tokenizers.ngram,
    identify: function (obj) {
      return obj.ID
    },
    prefetch: {
      url: wpApiSettings.root + 'dt/v1/locations-compact/',
      transform: function(data){
        return filterTypeahead(data, group.locations || [])
      },
      cache: false
    },
    remote: {
      url: wpApiSettings.root + 'dt/v1/locations-compact/?s=%QUERY',
      wildcard: '%QUERY',
      transform: function(data){
        return filterTypeahead(data, group.locations || [])
      }
    },
    initialize: false,
    local : []
  });

  let locationsTypeahead = $('.locations .typeahead')
  function loadLocationsTypeahead() {
    locationsTypeahead.typeahead({
      highlight: true,
      minLength: 0,
      autoselect: true,
    },
    {
      name: 'locations',
      source: function (q, sync, async) {
        return defaultFilter(q, sync, async, locations, group.locations)
      },
      display: 'name'
    })
  }
  locationsTypeahead.bind('typeahead:select', function (ev, sug) {
    locationsTypeahead.typeahead('val', '')
    group.locations.push(sug)
    add_typeahead_item(groupId, 'locations', sug.ID, sug.name)
    locationsTypeahead.typeahead('destroy')
    loadLocationsTypeahead()
  })
  loadLocationsTypeahead()


  /**
   * Addresses
   */
  var button = $('.address.details-edit.add-button')
  console.log(button)
  button.on('click', e=>{
    console.log(e)
    if ($('#new-address').length === 0 ) {
      let newInput = `<li>
        <textarea id="new-address"></textarea>
      </li>`
      $('.details-edit.address-list').append(newInput)
    }
  })
  //for a new address field that has not been saved yet
  $(document).on('change', '#new-address', function (val) {
    let input = $('#new-address')
    add_item_to_field(groupId, {"new-address":input.val()}).done(function (data) {
      if (data != groupId){
        //change the it to the created field
        input.attr('id', data)
        $('.details-list.address').append(`<li class="${data}">${input.val()}</li>`)
      }

    })
  })
  $(document).on('change', '.address-list textarea', function(){
    let id = $(this).attr('id')
    if (id && id !== "new-address"){
      save_field_api(groupId, {[id]: $(this).val()}).done(()=>{
        $(`.address.details-list .${id}`).text($(this).val())
      })

    }
  })


  /**
   * Members
   */

  $("#members-edit").on('click', function () {
    $('.members-edit').toggle()
  })
  let members = new Bloodhound({
    datumTokenizer: searchAnyPieceOfWord,
    queryTokenizer: Bloodhound.tokenizers.ngram,
    identify: function (obj) {
      return obj.ID
    },
    prefetch: {
      url: wpApiSettings.root + 'dt-hooks/v1/contacts/compact',
      transform: function(data){
        loadMembersTypeahead()
        return filterTypeahead(data, group.members || [])
      },
      cache: false
    },
    remote: {
      url: wpApiSettings.root + 'dt-hooks/v1/contacts/compact/?s=%QUERY',
      wildcard: '%QUERY',
      transform: function(data){
        return filterTypeahead(data, group.members || [])
      }
    },
    initialize: false,
    local : []
  });

  let membersTypeahead = $('#members .typeahead')
  function loadMembersTypeahead() {
    membersTypeahead.typeahead('destroy')
    membersTypeahead.typeahead({
        highlight: true,
        minLength: 0,
        autoselect: true,
      },
      {
        name: 'members',
        source: function (q, sync, async) {
          return defaultFilter(q, sync, async, members, group.members)
        },
        display: 'name'
      })
  }
  membersTypeahead.bind('typeahead:select', function (ev, sug) {
    membersTypeahead.typeahead('val', '')
    group.members.push(sug)
    add_typeahead_item(groupId, 'members', sug.ID, sug.name)
    loadMembersTypeahead()
  })
  loadMembersTypeahead()



  /**
   * Get the group fields from the api
   */

  get_group(groupId).done(function (groupData) {
    console.log(groupData)
    group = groupData
    if (groupData.end_date){
      endDatePicker.datepicker('setDate', groupData.end_date)
    }
    if (groupData.start_date){
      startDatePicker.datepicker('setDate', groupData.start_date)
    }
    if (groupData.assigned_to){
      $('.current-assigned').text(_.get(groupData, "assigned_to.display"))
    }
    locations.initialize()
    members.initialize()
  })


  /**
   * Comments and Activity
   */

  let comments = []
  let activity = []

  function refreshActivity() {
    get_activity(groupId).done(activityData=>{
      activityData.forEach(d=>{
        d.date = new Date(d.hist_time*1000)
      })
      activity = activityData
      display_activity_comment()
    })
  }

  let commentButton = $('#add-comment-button')
    .on('click', function () {
      commentButton.toggleClass('loading')
      let input = $("#comment-input")
      post_comment(groupId, input.val()).success(commentData=>{
        commentButton.toggleClass('loading')
        input.val('')
        commentData.comment.date = new Date(commentData.comment.comment_date_gmt + "Z")
        comments.push(commentData.comment)
        display_activity_comment()
      })
    })

  $.when(
    get_comment(groupId),
    get_activity(groupId)
  ).done(function(commentData, activityData){
    commentData[0].forEach(comment=>{
      comment.date = new Date(comment.comment_date_gmt + "Z")
    })
    comments = commentData[0]
    activityData[0].forEach(d=>{
      d.date = new Date(d.hist_time*1000)
    })
    activity = activityData[0]
    display_activity_comment("all")
  })

  function display_activity_comment(section) {
    current_section = section || current_section

    let commentsWrapper = $("#comments-wrapper")
    commentsWrapper.empty()
    let displayed = []
    if (current_section === "all"){
      displayed = _.union(comments, activity)
    } else if (current_section === "comments"){
      displayed = comments
    } else if ( current_section === "activity"){
      displayed = activity
    }
    displayed = _.orderBy(displayed, "date", "desc")
    let array = []

    displayed.forEach(d=>{
      let first = _.first(array)
      let name = d.comment_author || d.name
      let obj = {
        name: name,
        date: d.date,
        text:d.object_note ||  d.comment_content,
        comment: !!d.comment_content
      }


      let diff = (first ? first.date.getTime() : new Date().getTime()) - obj.date.getTime()
      if (!first || (first.name === name && diff < 60 * 60 * 1000) ){
        array.push(obj)
      } else {
        commentsWrapper.append(commentTemplate({
          name: array[0].name,
          date:formatDate(array[0].date),
          activity: array
        }))
        array = [obj]
      }
    })
    if (array.length > 0){
      commentsWrapper.append(commentTemplate({
        name: array[0].name,
        date:formatDate(array[0].date),
        activity: array
      }))
    }
  }

  let commentTemplate = _.template(`
  <div class="activity-block">
    <div><span><strong><%- name %></strong></span> <span class="comment-date"> <%- date %> </span></div>
    <div class="activity-text">
    <% _.forEach(activity, function(a){ 
        if (a.comment){ %>
            <p dir="auto" class="comment-bubble"> <%- a.text %> </p>
      <% } else { %> 
            <p class="activity-bubble">  <%- a.text %> </p>
    <%  } 
    }); %>
    </div>
  </div>`
  )

  function formatDate(date) {
    var hours = date.getHours();
    var minutes = date.getMinutes();
    var ampm = hours >= 12 ? 'pm' : 'am';
    hours = hours % 12;
    hours = hours ? hours : 12; // the hour '0' should be '12'
    minutes = minutes < 10 ? '0'+minutes : minutes;
    var strTime = hours + ':' + minutes + ' ' + ampm;
    var month = date.getMonth()+1
    month = month < 10 ? "0"+month.toString() : month
    return date.getFullYear() + "/" + date.getDate() + "/" + month + "  " + strTime;
  }

})


