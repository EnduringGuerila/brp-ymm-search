( function ($) {
  "use strict";

  $.widget("pektsekye.ymm", { 
    
    rootCategoryIds: [],    
    selectedValues : [],
    isRestoringCategories: false,
     
     
    _create : function () {

      $.extend(this, this.options);       

      this.garageContainer    = this.element.find('.ymm-garage');
      this.garageSelect       = this.element.find('.ymm-garage-select');        	
      this.extraContainer     = this.element.find('.ymm-extra');
      this.categoryContainer  = this.element.find('.ymm-category-container');    
      this.searchField        = this.element.find('.ymm-search-field');     
      this.searchAnySelButton = this.element.find('.ymm-submit-any-selection'); 
      
      
      this._on({ 
          "change .ymm-garage-select": $.proxy(this.preSelectDropdowns, this),        
          "click .ymm-remove-from-garage": $.proxy(this.garageRemove, this),      
          "change .ymm-select": $.proxy(this.loadLevel, this),
          "change .ymm-category-select": $.proxy(this.checkSubCategories, this),
          "submit form": $.proxy(this.submitSearch, this),                 
          "click button.ymm-submit-any-selection": $.proxy(this.submit, this),
          "click .ymm-clear-filter": $.proxy(this.clearFilter, this),  
          "click .ymm-search-all-link": $.proxy(this.searchAll, this)                                                                         
      }); 
               
      if (this.preCategories && this.preCategories.rootCategoryIds && this.lastLevelIsSelected()){
        this.rootCategoryIds = this.preCategories.rootCategoryIds;      
        this.categories = this.preCategories.categories;
                 
        this.addCategorySelect(this.rootCategoryIds);
        
        if (this.wordSearchEnabled){
          this.extraContainer.addClass('or-search');
          this.searchAnySelButton.hide();                  
        }                                             
        this.extraContainer.show();
        
        // Restore category selections after they're added
        this.restoreCategorySelections();                 
      }                           
    },
  
  
    preSelectDropdowns : function(e){
    
      var vehicle = $(e.target).val();
      
      if (vehicle){
      
        this.selectedValues = vehicle.split(',');
      
        if (!this.canShowExtra){
      
          this._submit(null, null, this.selectedValues);

        } else {

          var firstValue = this.selectedValues[0];
          var firstSelect = this.element.find('.ymm-select').first();
      
          var option;
          var valueChanged = false;
          
          var l = firstSelect[0].options.length;
          for (var i=0;i<l;i++){
            option = firstSelect[0].options[i];
            if (option.value == firstValue){
              option.selected = true;
              valueChanged = true;
              break;
            }  
          }
          
          if (valueChanged){
            this.loadLevel({target:firstSelect});
          } else {// remove not found values
            this.garageRemove();
          }
        }
      }
      
      this.garageSetSelected(vehicle);            
    },  
  
  
    garageSetSelected : function(vehicle){ 
      var cookie = Cookies.get(this.ymmCookieName);
      if (cookie){
        var selected = $.parseJSON(cookie);
        if (selected.vehicles && selected.vehicle != vehicle){
          selected.vehicle = vehicle;  
          Cookies.set(this.ymmCookieName, JSON.stringify(selected)); 
        }                
      }    
    },
    
    
    saveCategorySelections : function() {
      var categorySelections = [];
      this.element.find('.ymm-category-select').each(function() {
        var value = $(this).val();
        // Only save non-empty selections
        if (value && value !== '') {
          categorySelections.push(value);
        }
      });

      console.log('YMM Debug: Saving category selections:', categorySelections);

      if (categorySelections.length > 0) {
        var cookie = Cookies.get(this.ymmCookieName);
        var selected = {categories: categorySelections};

        if (cookie) {
          try {
            var selectedOld = $.parseJSON(cookie);
            if (selectedOld) {
              selected = selectedOld;
              selected.categories = categorySelections;
            }
          } catch (e) {}
        }

        Cookies.set(this.ymmCookieName, JSON.stringify(selected));
        console.log('YMM Debug: Cookie saved:', JSON.stringify(selected));
      } else {
        // If no categories are selected, remove categories from cookie but preserve vehicle data
        var cookie = Cookies.get(this.ymmCookieName);
        if (cookie) {
          try {
            var selected = $.parseJSON(cookie);
            if (selected) {
              delete selected.categories; // Remove categories key
              Cookies.set(this.ymmCookieName, JSON.stringify(selected));
              console.log('YMM Debug: Removed empty categories from cookie');
            }
          } catch (e) {}
        }
      }
    },
    
    
    restoreCategorySelections : function() {
      if (this.isRestoringCategories) {
        console.log('YMM Debug: Already restoring categories, skipping...');
        return;
      }
      
      var cookie = Cookies.get(this.ymmCookieName);
      console.log('YMM Debug: Restoring categories, cookie value:', cookie);

      if (cookie) {
        try {
          var selected = $.parseJSON(cookie);
          console.log('YMM Debug: Parsed cookie data:', selected);
          if (selected && selected.categories && selected.categories.length > 0) {
            // Filter out empty values from saved categories
            var validCategories = selected.categories.filter(function(cat) {
              return cat && cat !== '';
            });
            
            if (validCategories.length > 0) {
              console.log('YMM Debug: Applying category selections:', validCategories);
              this.isRestoringCategories = true;
              this.applyCategorySelections(validCategories, 0);
            }
          }
        } catch (e) {
          console.log('YMM Debug: Error parsing cookie:', e);
        }
      }
    },
    
    
    applyCategorySelections : function(savedSelections, selectionIndex) {
      var widget = this;
      
      console.log('YMM Debug: Applying selection at index', selectionIndex, 'value:', savedSelections[selectionIndex]);
      
      if (selectionIndex >= savedSelections.length) {
        console.log('YMM Debug: No more selections to restore, resetting flag');
        this.isRestoringCategories = false; // Reset flag when done
        return; // No more selections to restore
      }
      
      var currentSelection = savedSelections[selectionIndex];
      if (!currentSelection || currentSelection === '') {
        console.log('YMM Debug: Empty selection at index', selectionIndex, ', resetting flag');
        this.isRestoringCategories = false; // Reset flag on empty selection
        return; // Nothing to restore at this level
      }
      
      var categorySelects = this.element.find('.ymm-category-select');
      console.log('YMM Debug: Found', categorySelects.length, 'category selects');
      
      if (selectionIndex >= categorySelects.length) {
        console.log('YMM Debug: No select available for index', selectionIndex, ', resetting flag');
        this.isRestoringCategories = false; // Reset flag when no more selects
        return; // No more selects available
      }
      
      var currentSelect = $(categorySelects[selectionIndex]);
      var option = currentSelect.find('option[value="' + currentSelection + '"]');
      
      if (option.length > 0) {
        console.log('YMM Debug: Setting select to value:', currentSelection);
        currentSelect.val(currentSelection);
        
        // Don't trigger change event during restoration to prevent auto-submit
        // Instead manually handle the restoration logic
        
        // Check if this category has children and restore them
        if (this.categories[currentSelection] && this.categories[currentSelection].children) {
          console.log('YMM Debug: Category has children, adding subcategory select');
          // Add the subcategory select
          this.addCategorySelect(this.categories[currentSelection].children);

          // Restore the next level after a short delay to ensure the select is added
          setTimeout(function() {
            widget.applyCategorySelections(savedSelections, selectionIndex + 1);
          }, 10);
        } else {
          console.log('YMM Debug: Category has no children, restoration complete, resetting flag');
          this.isRestoringCategories = false; // Reset flag when complete
        }
        
        // Don't save during restoration - only save when user makes changes
      } else {
        console.log('YMM Debug: Option not found for value:', currentSelection, ', resetting flag');
        this.isRestoringCategories = false; // Reset flag on error
        // If the saved option is no longer available, don't continue with subsequent selections
      }
    },  
    garageAdd : function(vehicle){
    
      var selected = {vehicle:vehicle, vehicles:[vehicle]};
    
      var cookie = Cookies.get(this.ymmCookieName);
      if (cookie){
        var selectedOld;
        
        try {
          selectedOld = $.parseJSON(cookie);                        		
        } catch (e){}
        
        if (selectedOld && selectedOld.vehicles && selectedOld.vehicles.length){ 
          selected.vehicles = selectedOld.vehicles;
          if (selectedOld.vehicles.indexOf(vehicle) == -1){
            if (selectedOld.vehicles.length > 9){ // limit garage to 10 values
              selected.vehicles.shift();
            }  
            selected.vehicles.push(vehicle);
            selected.vehicles.sort($.proxy(this.sortCaseIns, this));
          }
        }
        
        // Preserve existing categories when adding vehicle
        if (selectedOld && selectedOld.categories) {
          selected.categories = selectedOld.categories;
        }           
      }    
      
      Cookies.set(this.ymmCookieName, JSON.stringify(selected));         
    },  
  
  
    garageRemove : function(){
      var vehicle = this.garageSelect.val();
      if (vehicle == ''){
        return false;
      }  
      var cookie = Cookies.get(this.ymmCookieName);
      if (cookie){
        var selected = $.parseJSON(cookie);
        if (selected.vehicles){
          this.without(selected.vehicles, vehicle);
          if (selected.vehicle == vehicle){
            selected.vehicle = selected.vehicles[0] ? selected.vehicles[0] : '';
          }
          
          this.garageSelect[0].remove(this.garageSelect[0].selectedIndex);       
                
          Cookies.set(this.ymmCookieName, JSON.stringify(selected)); 
        }                
      }
      return false;    
    },


    clearFilter : function(){
      this.garageSetSelected('');
      return true;    
    },
    
    
    searchAll : function(){
    
      this.filterCategoryPage = 0;
      this.submitUrl = this.submitSearchUrl;
      this.canShowExtra = this.categorySearchEnabled || this.wordSearchEnabled;    
    
      var firstSelect = this.element.find('.ymm-select').first();
      
      this.disableLevels(firstSelect);
            
      firstSelect[0].length = 1;
        
      var l = this.firstLevelOptions.length;		  
      for (var i=0;i<l;i++){
        firstSelect[0].options[i+1] = new Option(this.firstLevelOptions[i], this.firstLevelOptions[i]);
      } 
      
      if (this.garageEnabled){          
        var cookie = Cookies.get(this.ymmCookieName);
        if (cookie){
          var selected = $.parseJSON(cookie);
          if (selected.vehicles){
            var vehicle;
            var l = selected.vehicles.length;		  
            for (var i=0;i<l;i++){
              vehicle = selected.vehicles[i];
              var parts = vehicle.split(',');
              for (var p = 0; p < parts.length; p++) {
                parts[p] = $.trim(parts[p]);
              }
              var vehicleLabel = parts.length >= 4 ? parts.slice(parts.length - 3).join(' ') : parts.join(' ');
              this.garageSelect[0].options[i+1] = new Option(vehicleLabel, vehicle);
            }
            if (selected.vehicle){
              this.garageSelect.val(selected.vehicle).change();
            }
            this.garageContainer.show();          
          }                
        }       
      }
      
      var titleSpan = this.element.find('.ymm-title span');
      if (titleSpan.length){
        titleSpan.text(this.searchTitle);        
      } else {
        this.element.closest('div.widget').find('span.widget-title').text(this.searchTitle);
      }
      this.element.find('span.ymm-garage-text').text(this.garageText);
      this.searchAnySelButton.prop('title', this.searchButtonText).text(this.searchButtonText);
      this.element.find('span.ymm-filter-links').hide();
      
      return false;    
    },
    
    
    loadLevel : function(e){

      var element = $(e.target);    
      var value = element.val();
 
      this.disableLevels(element);
      this.hideExtra();
            
      if (value != ''){
    
        var values = [];
        var selects = this.element.find('.ymm-select');
        selects.each(function() {
          values.push($(this).val());                
          if (this == element[0])
            return false;  
        });         
              
        var nextLevel = values.length;
                 
        if (selects.length == values.length){// last drop-down is selected 
          if (this.canShowExtra)
            this.showExtra(values);
          else
            this.submit();
        } else {
          var categoryId = this.filterCategoryPage ? this.categoryId : 0;
          var widget = this;
          $.ajax({
              type: 'GET',
              url: this.ajaxShortUrl ? this.ajaxShortUrl : this.ajaxUrl,
              async: true,
              data: {action:'ymm_selector_fetch', cId:categoryId, 'values[]':values},
              dataType: 'json'
          }).done(
              function (data) {
                if (!data.error){           
                  if (data.length == 0){//there are no values for the next drop-down
                    //widget.submit();
                  } else {                
                    widget.enableLevel(element, data, nextLevel);
                  }
                }  
              }
            );
            this.showLoadingText(element);   
        }  
      
      }
    
    },
  
    showLoadingText : function(element){
      var select;
      
      if (this.isHorizontal)
        select = $(element).closest('.level').next().find('.ymm-select');
      else  
        select = $(element).next('.ymm-select'); 
           
      select[0].options[1] = new Option('Loading...', '');
      select[0].selectedIndex = 1;   
    },  
  
  
    enableLevel : function(element, options, level){
    
      if (this.isHorizontal)
        var select = $(element).closest('.level').next().find('.ymm-select');
      else  
        var select = $(element).next('.ymm-select');

      // Sort options using natural sorting for better model ordering
      options.sort($.proxy(this.naturalSort, this));
    
      var l = options.length;		  
      for (var i=0;i<l;i++)
        select[0].options[i+1] = new Option(options[i], options[i]);
      
      select[0].disabled = false; 
      select.removeClass('disabled');  
      
      if (this.garageEnabled){
        var selectedValue = this.selectedValues[level];
        if (selectedValue){
        
          var option;
          var valueChanged = false;
          
          var l = select[0].options.length;
          for (var i=0;i<l;i++){
            option = select[0].options[i];
            if (option.value == selectedValue){
              option.selected = true;
              valueChanged = true;
              break;
            }  
          }
          
          if (valueChanged){
            this.loadLevel({target:select});
          } else {// remove not found values
            this.garageRemove();
          } 
                
          this.selectedValues[level] = '';
        }
      }         
    },


    disableLevels : function(element){
      var disable = false;
      this.element.find('.ymm-select').each(function() {
        if (disable){
          this.length = 1;
          this.disabled = true;
          $(this).addClass('disabled');          
        }                  
        if (this == element[0])
          disable = true;  
      });   
    }, 
      

    showExtra : function(values){ 
  
      this.hideExtra(); 
             
      if (this.lastLevelIsSelected()){
        
        this.rootCategoryIds = [];
        this.categories = {}; 
          
        if (this.categorySearchEnabled){
          var jqxhr = this.loadCategoryDropdowns(values);
          if (jqxhr){
            jqxhr.always($.proxy(function(){           
              if (this.rootCategoryIds.length > 0){
                this.addCategorySelect(this.rootCategoryIds);
                
                // Always try to restore previous category selections
                this.restoreCategorySelections();
                
                if (this.wordSearchEnabled){
                  this.extraContainer.addClass('or-search');                  
                }                  
              }                
              this.extraContainer.show();
              if (this.wordSearchEnabled){              
                this.searchAnySelButton.hide();
              }                     
            },this));
          }       
        } else {
          this.extraContainer.show();
          if (this.wordSearchEnabled){          
            this.searchAnySelButton.hide();
          }
        }
            
      }	  
    },


    hideExtra : function(){
  
      if (this.categorySearchEnabled || this.wordSearchEnabled){
      
        this.extraContainer.hide();
      
        if (this.categorySearchEnabled)   
          this.removeSubCategories();
          
        if (this.wordSearchEnabled){
          this.extraContainer.removeClass('or-search');                  
          this.searchField.val('');         
        }                      
      }
         
      this.searchAnySelButton.show(); 
    },


    removeSubCategories : function(element){
           
      var isHorisontal = this.isHorizontal;            
           
      var startRemove = element == undefined ? true : false;
      this.element.find('.ymm-category-select').each(function() {
        if (startRemove){
          if (isHorisontal)
            $(this).closest('.level').remove();
          else
            $(this).remove();
        }                 
        if (!startRemove && this == element[0])
          startRemove = true;  
      });     
    },
 
 
    loadCategoryDropdowns : function(values){    
      var widget = this;
      var jqxhr = $.ajax({
          type: 'GET',
          url: this.ajaxShortUrl ? this.ajaxShortUrl : this.ajaxUrl,
          async: true,
          data: {action:'ymm_selector_get_categories', 'values[]':values},
          dataType: 'json'
      }).done(
          function (data) {
            if (!data.error && data.rootCategoryIds){            
              $.extend(widget, data);                         
            }  
          }
        );
        
      return jqxhr;              
    },

     
    addCategorySelect : function(categoryIds){
    
      var selectHtml = '<select class="ymm-category-select"></select>';

      if (this.isHorizontal){  
        selectHtml = '<div class="level">' +selectHtml+ '</div>';
        this.categoryContainer.find('.ymm-clear').before(selectHtml); 
      } else {
        this.categoryContainer.append(selectHtml);
      }     
         
      var select = this.element.find('.ymm-category-select').last();
      
      select[0].options[0] = new Option(this.categoryDefOptionTitle, '');
      
      // Create an array of category objects for sorting
      var categoryOptions = [];
      var l = categoryIds.length;		  
      for (var i=0;i<l;i++){
        var cId = categoryIds[i];
        categoryOptions.push({
          id: cId,
          title: this.categories[cId].title
        });
      }
      
      // Sort categories alphabetically by title
      categoryOptions.sort(function(a, b) {
        return a.title.toLowerCase().localeCompare(b.title.toLowerCase());
      });
      
      // Add sorted options to the select
      for (var i=0;i<categoryOptions.length;i++){
        select[0].options[i+1] = new Option(categoryOptions[i].title, categoryOptions[i].id);
      }    
    
    },


    checkSubCategories : function(e){
      // Don't process changes during category restoration
      if (this.isRestoringCategories) {
        console.log('YMM Debug: Skipping checkSubCategories during restoration');
        return;
      }
      
      var element = $(e.target)

      this.removeSubCategories(element);

      var cId = element.val();
      if (cId != ''){     
        if (this.categories[cId].children){
          this.addCategorySelect(this.categories[cId].children);
        } else {
          this.submitCategory(cId);
        }
      }
      
      // Save category selections when they change
      this.saveCategorySelections();
    },  
      
      
    getLastSelectedCategory : function(){
    
      var categoryId = null;
      
      var widget = this;
      this.element.find('.ymm-category-select').each(function() {
        var cId = $(this).val();
        if (cId && widget.categories[cId].url){
          categoryId = cId;
        }   
      });
      
      return categoryId;
    },
    
          
    submit : function(){

      if (!this.firstLevelIsSelected()){
        return;
      }
      
      if (this.rootCategoryIds.length > 0){
        var categoryId = this.getLastSelectedCategory();
        if (categoryId){
          this.submitCategory(categoryId);
          return;
        }
      }        
        
      this._submit();
    },

    
    submitCategory : function(categoryId){
    
      var categoryUrl = this.categories[categoryId].url;

      this._submit(null, categoryUrl);
    },


    submitSearch : function(){

      var searchWord = this.searchField.val();         

      if (searchWord == '' && this.rootCategoryIds.length > 0){
        var categoryId = this.getLastSelectedCategory();
        if (categoryId){
          this.submitCategory(categoryId);
          return false;
        }
      }      
      
      this._submit(searchWord);    

      return false;
    },


    _submit : function(searchWord, categoryUrl, garageValues){

      if (!garageValues){
        if (this.lastLevelIsSelected()){
          var values = [];
          this.element.find('.ymm-select').each(function() {
            values.push($(this).val());                  
          });     
          this.garageAdd(values.join(','));
        } else {
          this.garageSetSelected('');
        }
      }
      
      var searchWord = searchWord ? searchWord : '';
      
      var params = (this.isCategoryPage && this.filterCategoryPage) || categoryUrl ? {} : {s:searchWord, ymm_search:1, post_type:'product'};
      
      var values = garageValues ? this.getValuesAsParams(garageValues) : this.getLevelValuesAsParams();
      $.extend(params, values);  
    
      var url = categoryUrl ? categoryUrl : this.submitUrl;
      
      if (url == ''){
        var currentUrl = window.location.href;
        if (currentUrl.indexOf('/page/') != -1){
          url = currentUrl.replace(/\/page\/\d+\//,'/').replace(/\?.*/, '');
        }  
      }
            
      window.location.href = url + '?' + $.param(params);
    },
    

    getLevelValuesAsParams : function(){  
  
      var params = {};
      var pNames = this.levelParameterNames;
      this.element.find('.ymm-select').each(function(i) {
        var v = $(this).val();
        if (v){
          params[pNames[i]] = v;
        }   
      });     
      
      return params;  	  
    },


    getValuesAsParams : function(garageValues){  
  
      var params = {};     
      var pNames = this.levelParameterNames;
      var l = garageValues.length;
      for (var i=0;i<l;i++) {
        params[pNames[i]] = garageValues[i];   
      }     
      
      return params;  	  
    },
    
    
    firstLevelIsSelected : function(){
      return this.element.find('.ymm-select').first().val() != '';
    },
    
   
    lastLevelIsSelected : function(){
      return this.element.find('.ymm-select').last().val() != '';
    },
    
    
    without : function(a, v){
      var i = a.indexOf(v);
      if (i != -1)
        a.splice(i, 1);
    },
    
    
    sortCaseIns : function(a, b){
      a = a.toLowerCase();
      b = b.toLowerCase();
      if (a == b) return 0;
      if (a > b) return 1;
    },
    
    
    naturalSort : function(a, b) {
      // Parse model strings like "CRF 450R", "TC 65", "85 SX", "150 XC-W", "1090 Adv", "1290 Super Adv"
      var parseModel = function(model) {
        // Remove extra spaces and normalize
        var normalized = model.trim().replace(/\s+/g, ' ');
        
        // Try to match: [letters] [number] [suffix] or [number] [suffix]
        var match = normalized.match(/^([A-Za-z]*)\s*(\d{1,4})\s*(.*)$/);
        
        if (match) {
          return {
            prefix: (match[1] || '').toLowerCase(),
            number: parseInt(match[2], 10),
            suffix: (match[3] || '').toLowerCase(),
            original: model
          };
        }
        
        // Fallback: treat entire string as prefix if no number found
        return {
          prefix: normalized.toLowerCase(),
          number: 0,
          suffix: '',
          original: model
        };
      };
      
      var parsedA = parseModel(a);
      var parsedB = parseModel(b);
      
      // First sort by prefix (alphabetically)
      if (parsedA.prefix !== parsedB.prefix) {
        return parsedA.prefix.localeCompare(parsedB.prefix);
      }
      
      // Then sort by number (numerically)
      if (parsedA.number !== parsedB.number) {
        return parsedA.number - parsedB.number;
      }
      
      // Finally sort by suffix (alphabetically)
      return parsedA.suffix.localeCompare(parsedB.suffix);
    }	                  
    
            
  });
  
})(jQuery);            













