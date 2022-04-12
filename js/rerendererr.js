/**
 * When trying to save and make ready, check if there are errors. If they are
 * display a red border to the elements that are missing.
 */
if (document.getElementById('fitem_id_criteria').classList.contains('has-danger')) {
    
    Array.from((document.getElementById('criteriaTable').querySelector('tbody').children)).forEach(function (tr) {
        
        if (!tr.classList.contains('result-r')) {

            Array.from(tr.children).forEach(function (th) {
                if (th.classList.contains('fr-header') && th.classList.contains('crit-desc')) {
                    if (th.children[0].value == '') {
                        th.children[0].classList.add('border-danger-fr');
                    }
                } else if (!(th.classList.contains('fr-header') && th.classList.contains('act'))) {
                    Array.from(th.children).forEach(function (ch, index) {
                        if (!ch.classList.contains('action-el')) {
                            Array.from(ch.children).forEach(function (t) {
                               
                                if (t.rows.length == 1) { // Criterion has to have more than one level to be ready to use.
                                    tr.classList.add('border-danger-fr');
                                }
                                var zerocounter = 0;
                                Array.from(t.querySelectorAll('tr')).forEach(function (itd, index) {
                                  
                                    Array.from(itd.children).forEach(function (tdch, index) {
                                        Y.log(itd.children);
                                        if (tdch.classList.contains('level-mark')) {
                                          
                                            Array.from(tdch.children).forEach(function (mark) {
                                                if(mark.value == '0-0') {zerocounter++};
                                                if (mark.value == '') {
                                                    mark.classList.add('border-danger-fr');
                                                } 
                                            });
                                          
                                           
                                        } else {
                                            Array.from(tdch.children).forEach(function (descriptor) {
                                                if (descriptor.classList.contains('standard-desc-container')) {

                                                    Array.from(descriptor.children).forEach(function (desc) {

                                                        if (desc.classList.contains('checkbox-container')) {

                                                            if (desc.querySelector('.standard-desc').value == '') {
                                                                desc.querySelector('.standard-desc').classList.add('border-danger-fr');
                                                            }
                                                        }
                                                    })
                                                }
                                            });
                                        }

                                        // Finally check that levels have different marks.
                                       
                                        if (zerocounter > 1) {
                                            tr.classList.add('border-danger-fr');
                                        }
                                    })
                                })
                            })
                        }
                    });
                }
            });
        }
    });

    //Rebuild the criterionjson in case the error came because hte user didnt add a level to the descriptor.
    //For some reason, the form doesn't refresh json
}