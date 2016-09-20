var wysihtmlParserRules = {
  classes: {
    'wysiwyg-text-align-left': 1,
    'wysiwyg-text-align-center': 1,
    'wysiwyg-text-align-right': 1
  },
  tags: {
    'style': {
      'remove': 1
    },
    'b': 1,
    'strong': { 'rename_tag': 'b' },
    'u': 1,
    'i': 1,
    'ul': 1,
    'li': 1,
    'div': 1,
    'br': 1,
    'h1': 1,
    'h2': 1,
    'h3': 1
  }
};
