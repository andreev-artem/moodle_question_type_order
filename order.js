/*
This code is adapted from YAHOO.example.DDList and YAHOO.example.DDListItem
from YUI version 2.2.2.

Software License Agreement (BSD License)
Copyright (c) 2007, Yahoo! Inc.
All rights reserved.

Redistribution and use of this software in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

    * Redistributions of source code must retain the above copyright notice,
      this list of conditions and the following disclaimer.
    * Redistributions in binary form must reproduce the above copyright notice,
      this list of conditions and the following disclaimer in the documentation
      and/or other materials provided with the distribution.
    * Neither the name of Yahoo! Inc. nor the names of its contributors may
      be used to endorse or promote products derived from this software without
      specific prior written permission of Yahoo! Inc.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE
LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
POSSIBILITY OF SUCH DAMAGE.

*/

(function() {

var YAHOO;
var Dom;
var Event;
var DDM;

var MoodleDDListItem = function(id, sGroup, config) {

    MoodleDDListItem.superclass.constructor.call(this, id, sGroup, config);

    var el = this.getDragEl();
    Dom.setStyle(el, "opacity", 0.67); // The proxy is slightly transparent

    this.goingLeft = false;
    this.goingUp = false;
    this.lastX = 0;
    this.lastY = 0;
};

var MoodleDDListItem_extend = {

    startDrag: function(x, y) {

        // make the proxy look like the source element
        var dragEl = this.getDragEl();
        var clickEl = this.getEl();
        Dom.setStyle(clickEl, "visibility", "hidden");

        dragEl.innerHTML = clickEl.innerHTML;

        Dom.setStyle(dragEl, "color", Dom.getStyle(clickEl, "color"));
        Dom.setStyle(dragEl, "backgroundColor", Dom.getStyle(clickEl, "backgroundColor"));
        Dom.setStyle(dragEl, "border", "2px solid gray");
        Dom.setStyle(dragEl, "margin", "4px");
        Dom.setStyle(dragEl, "padding", "4px 0px 0px 4px");
    },

    endDrag: function(e) {

        var srcEl = this.getEl();
        var proxy = this.getDragEl();

        // Show the proxy element and animate it to the src element's location
        Dom.setStyle(proxy, "visibility", "");
        var a = new YAHOO.util.Motion(
            proxy, {
                points: {
                    to: Dom.getXY(srcEl)
                }
            },
            0.2,
            YAHOO.util.Easing.easeOut
        )
        var proxyid = proxy.id;
        var thisid = this.id;

        // Hide the proxy and show the source element when finished with the animation
        a.onComplete.subscribe(function() {
                Dom.setStyle(proxyid, "visibility", "hidden");
                Dom.setStyle(thisid, "visibility", "");
            });
        a.animate();
        
        M.order.SetHiddens(thisid);
    },

    onDrag: function(e) {

        // Keep track of the direction of the drag for use during onDragOver
        var x = Event.getPageX(e);
        var y = Event.getPageY(e);

        if (x < this.lastX) {
            this.goingLeft = true;
        } else if (x > this.lastX) {
            this.goingLeft = false;
        }
        if (y < this.lastY) {
            this.goingUp = true;
        } else if (y > this.lastY) {
            this.goingUp = false;
        }

        this.lastX = x;
        this.lastY = y;
    },

    onDragOver: function(e, id) {

        var srcEl = this.getEl();
        var destEl = Dom.get(id);

        // We are only concerned with list items, we ignore the dragover
        // notifications for the list.
        if (destEl.nodeName.toLowerCase() == "li") {
            var orig_p = srcEl.parentNode;
            var p = destEl.parentNode;

            if (this.goingUp && this.goingLeft) {
                p.insertBefore(srcEl, destEl); // insert above/left
            } else if (!this.goingUp && !this.goingLeft) {
                p.insertBefore(srcEl, destEl.nextSibling); // insert below/right
            } else if (this.goingUp || this.goingLeft) {
                p.insertBefore(srcEl, destEl); // insert above/left
            } else {
                p.insertBefore(srcEl, destEl.nextSibling); // insert below/right
            }

            DDM.refreshCache();
        }
    }
};

function initModuleGlobals(Y) {
    YAHOO = Y.YUI2;
    Dom = YAHOO.util.Dom;
    Event = YAHOO.util.Event;
    DDM = YAHOO.util.DragDropMgr;
    YAHOO.extend(MoodleDDListItem, YAHOO.util.DDProxy, MoodleDDListItem_extend);
};

M.order = {}

// Initialize the list items as dragdrop items
M.order.Init = function(Y, vars) {
    initModuleGlobals(Y);

    var ablock = Dom.get("ablock_" + vars.qid);
    ablock.innerHTML = vars.ablockcontent;

    if (vars.readonly) return;

    for (i = 0; i < vars.stemscount; i = i + 1) {
        new MoodleDDListItem('li_' + vars.qid + '_' + i, vars.qid);
    }
}

M.order.SetHiddens = function(liid) {
    var Dom = YAHOO.util.Dom;
    var items = Dom.get(liid).parentNode.getElementsByTagName("li");
    
    for (i = 0; i < items.length; i = i + 1) {
        inputhidden = Dom.get(items[i].getAttribute("name"));
        inputhidden.value = i + 1;
    }
}

M.order.OnClickDontKnow = function(qid) {
    var Dom = YAHOO.util.Dom;
    var ch = Dom.get('ch_' + qid);
    var ul = Dom.get('ul_' + qid);
    
    if (ch.checked) {
        Dom.addClass(ul, "deactivateddraglist");
        Dom.removeClass(ul, "draglist");
    }
    else {
        Dom.addClass(ul, "draglist");
        Dom.removeClass(ul, "deactivateddraglist");
    }
}

// Set the hidden response variables according to the position of the items in the
// specified list
function ddOrderingSetHiddens(event, names) {;
    var ulname = names.ulname;
    var respname = names.respname;

    // Get the list
    var Dom = YAHOO.util.Dom;
    var ul = Dom.get(ulname);

    // Get all items in the list
    var items = ul.getElementsByTagName("li");
    var itemslength = items.length;
    var li = null;

    // Assign each corresponding hidden variable the value of the item's position
    for (i = 0; i < itemslength; i = i + 1) {
        itemparts = items[i].id.split("_");
        li = Dom.get(respname + itemparts[1]);
        li.value = i + 1;
    }
}

// Modify the list style and hidden defaultresponse variable when the defaultresponse checkbox
// is checked or unchecked
function processGradeCheckbox(event, names) {
    var ulname = names.ulname;
    var defaultresponsename = names.defaultresponsename;

    // Get the checkbox, list, and corresponding hidden input
    var Dom = YAHOO.util.Dom;
    var ul = Dom.get(ulname);
    var checkbox = Dom.get(defaultresponsename);

    // If the checkbox is checked, set the deactivated style and set the hidden defaultresponse
    // variable to yes
    if (checkbox.checked) {
        Dom.addClass(ul, "deactivateddraglist");
        Dom.removeClass(ul, "draglist");
    }
    // Otherwise, remove the deactivated style and set the hidden defaultresponse variable to no
    else {
        Dom.addClass(ul, "draglist");
        Dom.removeClass(ul, "deactivateddraglist");
    }
}

// Cross-browser method to create variables with the name attribute
function createElementWithName(type, name) {
    try {
        // This should work in IE and throw an exception everywhere else
        var element = document.createElement('<' + type + ' name="' + name + '">');
    } catch (e) {
        // Compliant method that doesn't work in IE
        var element = document.createElement(type);
        element.setAttribute("name", name);
    }

    return element;
}

// Cross-browser method to create variables with the name attribute
function createElementWithNameandType(type, name, typeattr) {
    try {
        // This should work in IE and throw an exception everywhere else
        var element = document.createElement('<' + type + ' name="' + name + '" type="' + typeattr + '">');
    } catch (e) {
        // Compliant method that doesn't work in IE
        var element = document.createElement(type);
        element.setAttribute("name", name);
        element.setAttribute("type", typeattr);
    }

    return element;
}



})();